<?php

namespace App\Http\Controllers\API\V1\Financial;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Financial\InitiateMobilePaymentRequest;
use App\Http\Resources\Financial\MobilePaymentResource;
use App\Models\V1\Financial\Invoice;
use App\Models\V1\Financial\MobilePayment;
use App\Models\V1\Financial\Payment;
use App\Services\Payments\EMolaService;
use App\Services\Payments\MpesaService;
use App\Services\SchoolContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MobilePaymentController extends BaseController
{
    protected MpesaService $mpesaService;
    protected EMolaService $emolaService;
    protected SchoolContextService $schoolContextService;

    public function __construct(
        MpesaService $mpesaService,
        EMolaService $emolaService,
        SchoolContextService $schoolContextService
    ) {
        $this->middleware('auth:api');
        $this->mpesaService = $mpesaService;
        $this->emolaService = $emolaService;
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Initiate mobile payment
     */
    public function initiate(InitiateMobilePaymentRequest $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();

            $mobilePayment = MobilePayment::create([
                'student_id' => $request->student_id,
                'invoice_id' => $request->invoice_id,
                'provider' => $request->provider,
                'amount' => $request->amount,
                'phone' => $request->phone,
                'school_id' => $schoolId,
                'status' => 'pending',
            ]);

            // Initiate payment with provider
            $service = $request->provider === 'mpesa' ? $this->mpesaService : $this->emolaService;
            $result = $service->initiatePayment($mobilePayment);

            return $this->successResponse(
                new MobilePaymentResource($mobilePayment->fresh()->load(['student', 'invoice'])),
                'Payment initiated successfully',
                201
            );
        } catch (\Exception $e) {
            Log::error('Mobile payment initiation failed', [
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return $this->errorResponse(
                'Failed to initiate payment: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Handle payment callback (webhook)
     */
    public function callback(Request $request, string $provider): JsonResponse
    {
        try {
            $service = $provider === 'mpesa' ? $this->mpesaService : $this->emolaService;
            $mobilePayment = $service->handleCallback($request->all());

            if (!$mobilePayment) {
                return $this->errorResponse('Payment not found', 404);
            }

            // If payment completed, reconcile to student account
            if ($mobilePayment->status === 'completed') {
                $this->reconcilePayment($mobilePayment);
            }

            return $this->successResponse(
                new MobilePaymentResource($mobilePayment->fresh()->load(['student', 'invoice', 'payment'])),
                'Callback processed successfully'
            );
        } catch (\Exception $e) {
            Log::error('Mobile payment callback failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'request' => $request->all(),
            ]);

            return $this->errorResponse(
                'Failed to process callback: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Check payment status
     */
    public function checkStatus(string $paymentId): JsonResponse
    {
        try {
            $mobilePayment = MobilePayment::where('payment_id', $paymentId)->firstOrFail();

            $service = $mobilePayment->provider === 'mpesa' ? $this->mpesaService : $this->emolaService;
            $status = $service->checkStatus($mobilePayment);

            // Update status if changed
            if ($status['status'] !== $mobilePayment->status) {
                $mobilePayment->update([
                    'status' => $status['status'],
                    'transaction_id' => $status['transaction_id'] ?? $mobilePayment->transaction_id,
                ]);

                // Reconcile if completed
                if ($status['status'] === 'completed' && $mobilePayment->status !== 'completed') {
                    $this->reconcilePayment($mobilePayment->fresh());
                }
            }

            return $this->successResponse(
                new MobilePaymentResource($mobilePayment->fresh()->load(['student', 'invoice', 'payment'])),
                'Payment status retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to check payment status: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Reconcile payment to student account
     */
    protected function reconcilePayment(MobilePayment $mobilePayment): void
    {
        DB::beginTransaction();
        try {
            // Create payment record
            $payment = Payment::create([
                'invoice_id' => $mobilePayment->invoice_id,
                'amount' => $mobilePayment->amount,
                'method' => $mobilePayment->provider,
                'status' => 'completed',
                'paid_at' => $mobilePayment->completed_at ?? now(),
                'transaction_id' => $mobilePayment->transaction_id,
                'notes' => 'Mobile payment via ' . strtoupper($mobilePayment->provider),
            ]);

            // Link mobile payment to payment
            $mobilePayment->update([
                'payment_id_fk' => $payment->id,
            ]);

            // Update invoice status if exists
            if ($mobilePayment->invoice_id) {
                $invoice = Invoice::find($mobilePayment->invoice_id);
                if ($invoice) {
                    $remaining = $invoice->getRemainingBalance();
                    if ($remaining <= 0) {
                        $invoice->update([
                            'status' => 'paid',
                            'paid_at' => now(),
                        ]);
                    } elseif ($remaining < $invoice->total) {
                        $invoice->update(['status' => 'partially_paid']);
                    }
                }
            }

            DB::commit();

            // TODO: Send confirmation SMS
            // TODO: Generate receipt
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Payment reconciliation failed', [
                'mobile_payment_id' => $mobilePayment->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get current school ID helper
     */
    protected function getCurrentSchoolId(): ?int
    {
        try {
            return $this->schoolContextService->getCurrentSchoolId();
        } catch (\Exception $e) {
            return null;
        }
    }
}

