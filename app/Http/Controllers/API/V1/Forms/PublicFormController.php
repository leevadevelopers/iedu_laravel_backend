<?php

namespace App\Http\Controllers\API\V1\Forms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Forms\FormInstanceResource;
use App\Models\Forms\FormInstance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class PublicFormController extends Controller
{
    /**
     * Get public form instance data
     */
    public function show(Request $request): JsonResponse
    {
        // Extract token from URL parameter
        $token = $request->route('token');
        
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

        // Check if form is accessible
        $allowedStatuses = ['draft', 'in_progress'];
        if (!in_array($instance->status, $allowedStatuses) || !$instance->isPublicAccessValid()) {
            return response()->json([
                'error' => 'Form not accessible',
                'message' => 'This form is no longer accepting submissions'
            ], 403);
        }

        // Set tenant context
        if ($instance->tenant_id) {
            session(['tenant_id' => $instance->tenant_id]);
        }

        // Load template data
        $instance->load('template');

        return response()->json([
            'data' => new FormInstanceResource($instance),
            'message' => 'Form loaded successfully'
        ]);
    }

    /**
     * Update form data via public access
     */
    public function update(Request $request): JsonResponse
    {
        // Extract token and validate access
        $token = $request->route('token');
        $instance = $this->validatePublicAccess($token);
        
        if ($instance instanceof JsonResponse) {
            return $instance;
        }

        // Validate form data
        $validator = Validator::make($request->all(), [
            'form_data' => 'required|array',
            'current_step' => 'nullable|integer|min:1',
            'completion_percentage' => 'nullable|numeric|min:0|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Update form data
            $formData = $request->input('form_data', []);
            $currentStep = $request->input('current_step', $instance->current_step);
            $completionPercentage = $request->input('completion_percentage');

            $updateData = [
                'form_data' => array_merge($instance->form_data ?? [], $formData),
                'current_step' => $currentStep
            ];

            if ($completionPercentage !== null) {
                $updateData['completion_percentage'] = $completionPercentage;
            }

            $instance->update($updateData);

            // Log public access activity
            Log::info('Public form updated', [
                'instance_id' => $instance->id,
                'instance_code' => $instance->instance_code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'data' => new FormInstanceResource($instance),
                'message' => 'Form updated successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Public form update error', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to update form',
                'message' => 'An error occurred while saving the form'
            ], 500);
        }
    }

    /**
     * Submit form via public access
     */
    public function submit(Request $request): JsonResponse
    {
        // Extract token and validate access
        $token = $request->route('token');
        $instance = $this->validatePublicAccess($token);
        
        if ($instance instanceof JsonResponse) {
            return $instance;
        }

        // Validate submission data
        $validator = Validator::make($request->all(), [
            'form_data' => 'required|array',
            'submission_metadata' => 'nullable|array',
            'submission_metadata.name' => 'nullable|string|max:255',
            'submission_metadata.email' => 'nullable|email|max:255',
            'submission_metadata.phone' => 'nullable|string|max:20',
            'submission_metadata.organization' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if form can be submitted
            if ($instance->status !== 'draft' && $instance->status !== 'in_progress') {
                return response()->json([
                    'error' => 'Form cannot be submitted',
                    'message' => 'This form is no longer accepting submissions'
                ], 403);
            }

            // Update form data with final submission
            $formData = $request->input('form_data', []);
            $submissionMetadata = $request->input('submission_metadata', []);

            $instance->update([
                'form_data' => array_merge($instance->form_data ?? [], $formData),
                'status' => 'submitted',
                'submitted_at' => now(),
                'completion_percentage' => 100
            ]);

            // Create submission record with metadata
            $submission = $instance->submissions()->create([
                'tenant_id' => $instance->tenant_id,
                'submitted_by' => null, // Public submission
                'submission_data' => $instance->form_data,
                'submission_type' => 'public_submit',
                'submission_metadata' => $submissionMetadata
            ]);

            // Log public submission
            Log::info('Public form submitted', [
                'instance_id' => $instance->id,
                'instance_code' => $instance->instance_code,
                'submission_id' => $submission->id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => $submissionMetadata
            ]);

            return response()->json([
                'data' => new FormInstanceResource($instance),
                'message' => 'Form submitted successfully',
                'submission_id' => $submission->id
            ]);

        } catch (\Exception $e) {
            Log::error('Public form submission error', [
                'instance_id' => $instance->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to submit form',
                'message' => 'An error occurred while submitting the form'
            ], 500);
        }
    }

    /**
     * Validate form data
     */
    public function validate(Request $request): JsonResponse
    {
        // Extract token and validate access
        $token = $request->route('token');
        $instance = $this->validatePublicAccess($token);
        
        if ($instance instanceof JsonResponse) {
            return $instance;
        }

        $formData = $request->input('form_data', []);
        
        // Basic validation - in a real implementation, you'd validate against template rules
        $validationErrors = [];
        
        // Check required fields from template
        if ($instance->template) {
            foreach ($instance->template->steps ?? [] as $step) {
                foreach ($step['sections'] ?? [] as $section) {
                    foreach ($section['fields'] ?? [] as $field) {
                        if (($field['required'] ?? false) && empty($formData[$field['field_id'] ?? ''])) {
                            $validationErrors[] = [
                                'field' => $field['field_id'] ?? '',
                                'message' => 'This field is required'
                            ];
                        }
                    }
                }
            }
        }

        return response()->json([
            'valid' => empty($validationErrors),
            'errors' => $validationErrors
        ]);
    }

    /**
     * Validate public access token and return form instance
     */
    private function validatePublicAccess(string $token)
    {
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

        // Check if form is accessible
        $allowedStatuses = ['draft', 'in_progress'];
        if (!in_array($instance->status, $allowedStatuses) || !$instance->isPublicAccessValid()) {
            return response()->json([
                'error' => 'Form not accessible',
                'message' => 'This form is no longer accepting submissions'
            ], 403);
        }

        // Set tenant context
        if ($instance->tenant_id) {
            session(['tenant_id' => $instance->tenant_id]);
        }

        return $instance;
    }
}
