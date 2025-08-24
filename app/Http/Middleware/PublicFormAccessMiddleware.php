<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Forms\FormInstance;
use Symfony\Component\HttpFoundation\Response;

class PublicFormAccessMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract token from URL or query parameter
        $token = $this->extractToken($request);
        
        if (!$token) {
            return response()->json([
                'error' => 'Public access token is required',
                'message' => 'Invalid or missing access token'
            ], 401);
        }

        // Find form instance by public token
        $instance = FormInstance::byPublicToken($token)->first();
        
        if (!$instance) {
            return response()->json([
                'error' => 'Invalid access token',
                'message' => 'The provided access token is invalid or has expired'
            ], 404);
        }

        // Check if form is still accessible (not completed/submitted if configured)
        if (!$this->isFormAccessible($instance)) {
            return response()->json([
                'error' => 'Form not accessible',
                'message' => 'This form is no longer accepting submissions'
            ], 403);
        }

        // Store instance in request for controller access
        $request->attributes->set('public_form_instance', $instance);
        
        // Set tenant context for the instance
        if ($instance->tenant_id) {
            session(['tenant_id' => $instance->tenant_id]);
        }

        return $next($request);
    }

    /**
     * Extract token from request
     */
    private function extractToken(Request $request): ?string
    {
        // Check URL parameter first (for routes like /public/form/{token})
        $token = $request->route('token');
        
        if ($token) {
            return $token;
        }

        // Check query parameter
        return $request->query('token');
    }

    /**
     * Check if form is accessible for public submission
     */
    private function isFormAccessible(FormInstance $instance): bool
    {
        // Check if form is in a state that allows public access
        $allowedStatuses = ['draft', 'in_progress'];
        
        return in_array($instance->status, $allowedStatuses) && 
               $instance->isPublicAccessValid();
    }
}
