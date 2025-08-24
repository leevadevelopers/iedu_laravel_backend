<?php

namespace App\Http\Controllers\API\V1\Forms;

use App\Http\Controllers\Controller;
use App\Http\Resources\Forms\FormTemplateResource;
use App\Http\Resources\Forms\FormInstanceResource;
use App\Models\Forms\FormTemplate;
use App\Models\Forms\FormInstance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PublicFormTemplateController extends Controller
{
    /**
     * Get public form template data
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

        // Find form template by public token
        $template = FormTemplate::byPublicToken($token)->first();
        
        if (!$template) {
            return response()->json([
                'error' => 'Invalid access token',
                'message' => 'The provided access token is invalid or has expired'
            ], 404);
        }

        // Check if template can accept submissions
        if (!$template->canAcceptSubmissions()) {
            return response()->json([
                'error' => 'Form not accessible',
                'message' => 'This form is no longer accepting submissions'
            ], 403);
        }

        // Set tenant context
        if ($template->tenant_id) {
            session(['tenant_id' => $template->tenant_id]);
        }

        return response()->json([
            'data' => new FormTemplateResource($template),
            'message' => 'Form template loaded successfully',
            'submission_count' => $template->getSubmissionCount(),
            'can_submit' => $template->canAcceptSubmissions()
        ]);
    }

    /**
     * Create new form instance from template
     */
    public function createInstance(Request $request): JsonResponse
    {
        // Extract token and validate access
        $token = $request->route('token');
        $template = $this->validatePublicAccess($token);
        
        if ($template instanceof JsonResponse) {
            return $template;
        }

        // Get metadata fields from template configuration
        $metadataFields = $this->getMetadataFieldsFromTemplate($template);
        
        // Build dynamic validation rules
        $validationRules = [
            'submission_metadata' => 'nullable|array'
        ];
        
        // Add validation rules for each metadata field
        foreach ($metadataFields as $field) {
            $fieldRules = [];
            
            if ($field['required'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }
            
            // Add type-specific validation
            switch ($field['type'] ?? 'text') {
                case 'email':
                    $fieldRules[] = 'email';
                    $fieldRules[] = 'max:255';
                    break;
                case 'phone':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:20';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                default:
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:255';
                    break;
            }
            
            $validationRules["submission_metadata.{$field['key']}"] = implode('|', $fieldRules);
        }

        // Validate request data
        $validator = Validator::make($request->all(), $validationRules);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Check if template can accept new submissions
            if (!$template->canAcceptSubmissions()) {
                return response()->json([
                    'error' => 'Form not accessible',
                    'message' => 'This form is no longer accepting submissions'
                ], 403);
            }

            // Create new form instance
            $instance = FormInstance::create([
                'tenant_id' => $template->tenant_id,
                'form_template_id' => $template->id,
                'user_id' => null, // Public submission
                'instance_code' => $this->generateInstanceCode(),
                'form_data' => [],
                'status' => 'draft',
                'current_step' => 1,
                'completion_percentage' => 0,
                'created_by' => null, // Public submission
                'submission_type' => 'public',
                'submission_metadata' => $request->input('submission_metadata', [])
            ]);

            // Log public instance creation
            Log::info('Public form instance created', [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'instance_id' => $instance->id,
                'instance_code' => $instance->instance_code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent()
            ]);

            return response()->json([
                'data' => new FormInstanceResource($instance),
                'message' => 'Form instance created successfully',
                'instance_token' => $instance->id // Use instance ID as temporary token
            ]);

        } catch (\Exception $e) {
            Log::error('Public form instance creation error', [
                'template_id' => $template->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to create form instance',
                'message' => 'An error occurred while creating the form instance'
            ], 500);
        }
    }

    /**
     * Update form instance data
     */
    public function updateInstance(Request $request): JsonResponse
    {
        // Extract token and validate access
        $token = $request->route('token');
        $template = $this->validatePublicAccess($token);
        
        if ($template instanceof JsonResponse) {
            return $template;
        }

        // Get instance ID from request
        $instanceId = $request->input('instance_id');
        if (!$instanceId) {
            return response()->json([
                'error' => 'Instance ID is required',
                'message' => 'Please provide the instance ID'
            ], 400);
        }

        // Find the instance
        $instance = FormInstance::where('id', $instanceId)
                               ->where('form_template_id', $template->id)
                               ->where('submission_type', 'public')
                               ->first();

        if (!$instance) {
            return response()->json([
                'error' => 'Invalid instance',
                'message' => 'Form instance not found or not accessible'
            ], 404);
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
     * Submit form instance
     */
    public function submitInstance(Request $request): JsonResponse
    {
        // Extract token and validate access
        $token = $request->route('token');
        $template = $this->validatePublicAccess($token);
        
        if ($template instanceof JsonResponse) {
            return $template;
        }

        // Get instance ID from request
        $instanceId = $request->input('instance_id');
        if (!$instanceId) {
            return response()->json([
                'error' => 'Instance ID is required',
                'message' => 'Please provide the instance ID'
            ], 400);
        }

        // Find the instance
        $instance = FormInstance::where('id', $instanceId)
                               ->where('form_template_id', $template->id)
                               ->where('submission_type', 'public')
                               ->first();

        if (!$instance) {
            return response()->json([
                'error' => 'Invalid instance',
                'message' => 'Form instance not found or not accessible'
            ], 404);
        }

        // Validate submission data
        $metadataFields = $this->getMetadataFieldsFromTemplate($template);
        
        // Build dynamic validation rules
        $validationRules = [
            'form_data' => 'required|array',
            'submission_metadata' => 'nullable|array'
        ];
        
        // Add validation rules for each metadata field
        foreach ($metadataFields as $field) {
            $fieldRules = [];
            
            if ($field['required'] ?? false) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }
            
            // Add type-specific validation
            switch ($field['type'] ?? 'text') {
                case 'email':
                    $fieldRules[] = 'email';
                    $fieldRules[] = 'max:255';
                    break;
                case 'phone':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:20';
                    break;
                case 'number':
                    $fieldRules[] = 'numeric';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                default:
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:255';
                    break;
            }
            
            $validationRules["submission_metadata.{$field['key']}"] = implode('|', $fieldRules);
        }

        $validator = Validator::make($request->all(), $validationRules);

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
                    'message' => 'This form has already been submitted'
                ], 403);
            }

            // Update form data with final submission
            $formData = $request->input('form_data', []);
            $submissionMetadata = $request->input('submission_metadata', []);

            $instance->update([
                'form_data' => array_merge($instance->form_data ?? [], $formData),
                'status' => 'submitted',
                'submitted_at' => now(),
                'completion_percentage' => 100,
                'submission_metadata' => array_merge($instance->submission_metadata ?? [], $submissionMetadata)
            ]);

            // Log public submission
            Log::info('Public form submitted', [
                'template_id' => $template->id,
                'template_name' => $template->name,
                'instance_id' => $instance->id,
                'instance_code' => $instance->instance_code,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'metadata' => $submissionMetadata
            ]);

            return response()->json([
                'data' => new FormInstanceResource($instance),
                'message' => 'Form submitted successfully',
                'submission_id' => $instance->id
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
    public function validateInstance(Request $request): JsonResponse
    {
        // Extract token and validate access
        $token = $request->route('token');
        $template = $this->validatePublicAccess($token);
        
        if ($template instanceof JsonResponse) {
            return $template;
        }

        $formData = $request->input('form_data', []);
        
        // Basic validation against template rules
        $validationErrors = [];
        
        // Check required fields from template
        if ($template->steps) {
            foreach ($template->steps as $step) {
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
     * Validate public access token and return form template
     */
    private function validatePublicAccess(string $token)
    {
        if (!$token) {
            return response()->json([
                'error' => 'Public access token is required',
                'message' => 'Invalid or missing access token'
            ], 401);
        }

        // Find form template by public token
        $template = FormTemplate::byPublicToken($token)->first();
        
        if (!$template) {
            return response()->json([
                'error' => 'Invalid access token',
                'message' => 'The provided access token is invalid or has expired'
            ], 404);
        }

        // Set tenant context
        if ($template->tenant_id) {
            session(['tenant_id' => $template->tenant_id]);
        }

        return $template;
    }

    /**
     * Generate unique instance code
     */
    private function generateInstanceCode(): string
    {
        do {
            $code = 'INST-' . strtoupper(Str::random(8));
        } while (FormInstance::where('instance_code', $code)->exists());

        return $code;
    }

    /**
     * Get metadata fields from template configuration
     */
    private function getMetadataFieldsFromTemplate($template): array
    {
        // Default metadata fields if not configured
        $defaultFields = [
            [
                'key' => 'name',
                'label' => 'Name',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'Enter your name'
            ],
            [
                'key' => 'email',
                'label' => 'Email',
                'type' => 'email',
                'required' => false,
                'placeholder' => 'Enter your email'
            ],
            [
                'key' => 'phone',
                'label' => 'Phone',
                'type' => 'phone',
                'required' => false,
                'placeholder' => 'Enter your phone number'
            ],
            [
                'key' => 'organization',
                'label' => 'Organization',
                'type' => 'text',
                'required' => false,
                'placeholder' => 'Enter your organization'
            ]
        ];

        // Check if template has custom metadata fields configured
        $publicSettings = $template->public_access_settings ?? [];
        $metadataFields = $publicSettings['metadata_fields'] ?? null;

        if ($metadataFields && is_array($metadataFields)) {
            return $metadataFields;
        }

        return $defaultFields;
    }
}
