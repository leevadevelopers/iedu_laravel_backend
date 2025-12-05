<?php

namespace App\Http\Controllers\Subscription;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Subscription\Subscription;
use App\Models\Subscription\SubscriptionPackage;
use App\Models\Settings\Tenant;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SubscriptionController extends Controller
{
    /**
     * Grant a 30-day trial subscription when a new tenant is created.
     *
     * @param Tenant $tenant
     */
    public function grantTrialSubscription(Tenant $tenant)
    {
        // Check if the tenant already has an active subscription
        if ($tenant->subscriptions()->where('status', 'active')->exists()) {
            return;
        }

        return DB::transaction(function () use ($tenant) {
            // Find the Free/Trial plan (package id = 4)
            $freePackage = SubscriptionPackage::find(4);

            if (!$freePackage) {
                return;
            }

            // Assign the trial subscription with the Free package
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'subscription_package_id' => $freePackage->id,
                'start_date' => Carbon::now(),
                'end_date' => Carbon::now()->addDays(30),
                'status' => 'active',
                'auto_renew' => false,
            ]);

            return $subscription;
        });
    }

    /**
     * Subscribe a tenant or project to a package or renew an existing subscription.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function subscribe(Request $request)
    {
        Log::debug('Subscribe method called with data:', $request->all());
        
        $request->validate([
            'project_id' => 'nullable|exists:projects,id',
            'billing_cycle' => 'required|in:monthly,annual',
            'auto_renew' => 'boolean',
            'subscription_package_id' => 'required|exists:subscription_packages,id',
            'effective_date' => 'nullable|date',
        ]);
        
        return DB::transaction(function () use ($request) {
            // Retrieve tenant_id from request (set by TenantMiddleware)
            $tenantId = $request->get('tenant_id') ?? session('tenant_id');
            Log::debug('Tenant ID:', ['tenant_id' => $tenantId]);
            
            if (!$tenantId && !$request->project_id) {
                Log::warning('Missing tenant_id and project_id in subscription request');
                return response()->json(['error' => 'You must provide either a tenant_id (from middleware) or a project_id'], 422);
            }
            
            // Find the subscription package
            $package = SubscriptionPackage::findOrFail($request->subscription_package_id);
            Log::debug('Subscription package found:', $package->toArray());
            
            $startDate = $request->has('effective_date') 
                ? Carbon::parse($request->effective_date) 
                : Carbon::now();
            
            // Find any existing active subscription
            $query = Subscription::query();
                    
            if ($tenantId) {
                $query->where('tenant_id', $tenantId);
                Log::debug('Adding tenant filter to query:', ['tenant_id' => $tenantId]);
            }
                    
            if ($request->project_id) {
                $query->where('project_id', $request->project_id);
                Log::debug('Adding project filter to query:', ['project_id' => $request->project_id]);
            }
                    
            $existingActiveSubscription = $query->where('status', 'active')->first();
            
            if ($existingActiveSubscription) {
                Log::debug('Existing active subscription found', [
                    'id' => $existingActiveSubscription->id,
                    'package_id' => $existingActiveSubscription->subscription_package_id
                ]);
            } else {
                Log::debug('No existing active subscription found', []);
            }
            
            // If subscribing to the same plan that's already active, extend the existing subscription
            if ($existingActiveSubscription && $existingActiveSubscription->subscription_package_id == $package->id) {
                Log::debug('Extending existing subscription with same package ID', [
                    'package_id' => $package->id
                ]);
                
                // Calculate days to add based on billing cycle
                $daysToAdd = $request->billing_cycle === 'monthly' 
                    ? (int) ($package->duration_days ?? 30) 
                    : 365;
                
                Log::debug('Extending subscription', [
                    'billing_cycle' => $request->billing_cycle,
                    'days_to_add' => $daysToAdd,
                ]);
                
                // Use the same clean approach as extendSubscription
                $existingActiveSubscription->extend($daysToAdd, 'Auto-renewal extension', auth('api')->id() ?: 1);
                
                // Update auto-renew setting
                $existingActiveSubscription->update([
                    'auto_renew' => $request->has('auto_renew') ? $request->auto_renew : false,
                ]);
                
                Log::debug('Subscription extended successfully', [
                    'id' => $existingActiveSubscription->id,
                    'end_date' => $existingActiveSubscription->end_date
                ]);
                
                return response()->json([
                    'message' => 'Subscription extended successfully',
                    'subscription' => $existingActiveSubscription->fresh()
                ], 200);
            }
            
            // For a different package, mark existing subscription as 'cancelled' instead of deleting
            if ($existingActiveSubscription) {
                Log::debug('Cancelling existing subscription before creating new one', [
                    'id' => $existingActiveSubscription->id
                ]);
                
                // Check if there's already a cancelled subscription for this tenant/project
                // If so, delete it first to avoid unique constraint violation
                $cancelledQuery = Subscription::where('status', 'cancelled');
                
                if ($tenantId) {
                    $cancelledQuery->where('tenant_id', $tenantId);
                }
                
                if ($request->project_id) {
                    $cancelledQuery->where('project_id', $request->project_id);
                }
                
                $existingCancelledSubscription = $cancelledQuery->first();
                
                if ($existingCancelledSubscription) {
                    Log::debug('Deleting existing cancelled subscription to avoid constraint violation', [
                        'id' => $existingCancelledSubscription->id,
                        'tenant_id' => $tenantId,
                        'project_id' => $request->project_id
                    ]);
                    $existingCancelledSubscription->delete();
                }
                
                $existingActiveSubscription->update([
                    'status' => 'cancelled',
                    'end_date' => $startDate // End the current subscription now
                ]);
                
                Log::debug('Previous subscription cancelled', [
                    'id' => $existingActiveSubscription->id,
                    'end_date' => $existingActiveSubscription->end_date
                ]);
                
                $message = 'Previous subscription cancelled. New subscription created successfully';
            } else {
                Log::debug('No existing subscription to cancel', []);
                $message = 'Subscription created successfully';
            }
            
            // Create a new subscription
            $subscription = new Subscription();
            $subscription->tenant_id = $tenantId;
            $subscription->project_id = $request->project_id;
            $subscription->subscription_package_id = $package->id;
            $subscription->start_date = $startDate;
            
            // Calculate end date based on billing cycle (same logic as extension)
            $durationDays = $request->billing_cycle === 'monthly' 
                ? (int) ($package->duration_days ?? 30) 
                : 365;
            
            Log::debug('Creating new subscription', [
                'billing_cycle' => $request->billing_cycle,
                'duration_days' => $durationDays,
                'package_duration_days' => $package->duration_days,
            ]);
            
            $subscription->end_date = $startDate->copy()->addDays($durationDays);
            
            $subscription->status = 'active';
            $subscription->auto_renew = $request->has('auto_renew') ? $request->auto_renew : false;
            $subscription->save();
            
            Log::debug('New subscription created', [
                'id' => $subscription->id,
                'start_date' => $subscription->start_date,
                'end_date' => $subscription->end_date,
             ]);
            
            return response()->json([
                'message' => $message,
                'subscription' => $subscription
            ], 201);
        });
    }

    /**
     * List all subscriptions for the current tenant.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function listSubscriptions(Request $request)
    {
        try {
            $tenantId = $request->get('tenant_id') ?? session('tenant_id');
            
            if (!$tenantId) {
                return response()->json(['error' => 'No tenant context found'], 422);
            }
            
            $subscriptions = Subscription::with(['package', 'tenant'])
                ->where('tenant_id', $tenantId)
                ->orderBy('created_at', 'desc')
                ->get();
            
            return response()->json([
                'subscriptions' => $subscriptions,
                'count' => $subscriptions->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to list subscriptions', [
                'tenant_id' => $tenantId ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to list subscriptions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Extend an existing subscription.
     *
     * @param Request $request
     * @param Subscription $subscription
     * @return \Illuminate\Http\JsonResponse
     */
    public function extendSubscription(Request $request, Subscription $subscription)
    {
        $request->validate([
            'days' => 'required|integer|min:1'
        ]);

        try {
            // Get the authenticated user ID, fallback to 1 if not authenticated (for testing)
            $userId = auth('api')->id() ?: 1;
            
            $subscription->extend($request->days, 'Manual extension', $userId);

            return response()->json([
                'message' => 'Subscription extended successfully',
                'subscription' => $subscription->fresh(),
                'days_added' => $request->days
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to extend subscription', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to extend subscription: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all available subscription packages.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPackages()
    {
        try {
            $packages = SubscriptionPackage::where('status', 'active')
                ->orderBy('price', 'asc')
                ->get();
            
            return response()->json([
                'packages' => $packages,
                'count' => $packages->count()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get subscription packages', [
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'error' => 'Failed to get subscription packages: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel a subscription.
     *
     * @param Subscription $subscription
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelSubscription(Subscription $subscription)
    {
        if ($subscription->status !== 'active') {
            return response()->json(['error' => 'Subscription is not active'], 422);
        }

        return DB::transaction(function () use ($subscription) {
            // Check if there's already a cancelled subscription for this tenant
            // If so, delete it first to avoid unique constraint violation
            $existingCancelledSubscription = Subscription::where('tenant_id', $subscription->tenant_id)
                ->where('status', 'cancelled')
                ->where('id', '!=', $subscription->id)
                ->first();
            
            if ($existingCancelledSubscription) {
                Log::debug('Deleting existing cancelled subscription to avoid constraint violation', [
                    'id' => $existingCancelledSubscription->id,
                    'current_subscription_id' => $subscription->id
                ]);
                $existingCancelledSubscription->delete();
            }

            $subscription->update(['status' => 'cancelled']);

            return response()->json(['message' => 'Subscription cancelled successfully']);
        });
    }
}

