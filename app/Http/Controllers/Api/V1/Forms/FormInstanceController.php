<?php

namespace App\Http\Controllers\API\V1\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\CreateFormInstanceRequest;
use App\Http\Requests\Forms\UpdateFormInstanceRequest;
use App\Http\Resources\Forms\FormInstanceResource;
use App\Http\Resources\Forms\FormInstanceCollection;
use App\Models\Forms\FormInstance;
use App\Models\Forms\FormTemplate;
use App\Services\Forms\FormIntelligenceService;
use App\Services\Forms\WorkflowIntegrationService;
use App\Services\Forms\FormTriggerService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FormInstanceController extends Controller
{
    protected $intelligenceService;
    protected $workflowService;

    public function __construct(
        FormIntelligenceService $intelligenceService,
        WorkflowIntegrationService $workflowService
    ) {
        $this->intelligenceService = $intelligenceService;
        $this->workflowService = $workflowService;
    }

    /**
     * List form instances for current user/tenant
     */
    public function index(Request $request): JsonResponse
    {
        $query = FormInstance::with(['template', 'user'])
            ->when($request->status, fn($q, $status) => $q->where('status', $status))
            ->when($request->template_id, fn($q, $templateId) => $q->where('form_template_id', $templateId))
            ->when($request->user_id, fn($q, $userId) => $q->where('user_id', $userId))
            ->when($request->category, function($q, $category) {
                return $q->whereHas('template', function($templateQuery) use ($category) {
                    $templateQuery->where('category', $category);
                });
            })
            ->when($request->search, function($q, $search) {
                return $q->where(function($query) use ($search) {
                    $query->where('instance_code', 'like', "%{$search}%")
                          ->orWhereHas('template', function($templateQuery) use ($search) {
                              $templateQuery->where('name', 'like', "%{$search}%");
                          });
                });
            })
            ->latest();

        // Filter by user's own forms if not admin
        if (!auth('api')->user()->hasTenantPermission('forms.view_all')) {
            $query->where('user_id', auth('api')->id());
        }

        $instances = $query->paginate($request->per_page ?? 20);

        // Get category-specific stats if category is provided
        $categoryStats = null;
        if ($request->category) {
            $categoryStats = $this->getCategoryStats($request->category);
        }

        return response()->json([
            'data' => FormInstanceResource::collection($instances),
            'meta' => [
                'pagination' => [
                    'current_page' => $instances->currentPage(),
                    'per_page' => $instances->perPage(),
                    'total' => $instances->total(),
                    'last_page' => $instances->lastPage()
                ],
                'stats' => $this->getInstanceStats(),
                'category_stats' => $categoryStats
            ]
        ]);
    }

    /**
     * Get specific form instance
     */
    public function show(FormInstance $instance): JsonResponse
    {
        // $this->authorize('view', $instance);

        return response()->json([
            'data' => new FormInstanceResource($instance->load(['template', 'user', 'submissions']))
        ]);
    }

    /**
     * Generate public access token for form instance
     */
    public function generatePublicToken(Request $request, FormInstance $instance): JsonResponse
    {
        $user = $request->user();
        
        // Check if user has permission to generate public tokens
        // User needs either forms.admin OR forms.manage_public_access permission
        if (!$user->hasTenantPermission(['forms.admin', 'forms.manage_public_access'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to generate public access tokens. Required permissions: forms.admin or forms.manage_public_access'
            ], 403);
        }

        try {
            $token = $instance->generatePublicAccessToken();
            $publicUrl = $instance->getPublicAccessUrl();

            return response()->json([
                'data' => [
                    'instance_id' => $instance->id,
                    'instance_code' => $instance->instance_code,
                    'public_access_token' => $token,
                    'public_access_url' => $publicUrl,
                    'expires_at' => $instance->public_access_expires_at?->toISOString()
                ],
                'message' => 'Public access token generated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to generate public access token',
                'message' => 'An error occurred while generating the public access token'
            ], 500);
        }
    }

    /**
     * Revoke public access token for form instance
     */
    public function revokePublicToken(Request $request, FormInstance $instance): JsonResponse
    {
        $user = $request->user();
        
        // Check if user has permission to revoke public tokens
        if (!$user->hasTenantPermission(['forms.admin', 'forms.manage_public_access'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to revoke public access tokens'
            ], 403);
        }

        try {
            $instance->revokePublicAccessToken();

            return response()->json([
                'data' => [
                    'instance_id' => $instance->id,
                    'instance_code' => $instance->instance_code,
                    'public_access_enabled' => false
                ],
                'message' => 'Public access token revoked successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to revoke public access token',
                'message' => 'An error occurred while revoking the public access token'
            ], 500);
        }
    }

    /**
     * Create new form instance from template
     */
    public function store(CreateFormInstanceRequest $request): JsonResponse
    {
        $template = FormTemplate::findOrFail($request->form_template_id);

        // $this->authorize('create', [FormInstance::class, $template]);

        $instance = DB::transaction(function () use ($request, $template) {
            // Create form instance
            $instance = FormInstance::create([
                'tenant_id' => session('tenant_id') ?? auth()->user()->tenant_id ?? 1,
                'form_template_id' => $template->id,
                'user_id' => auth()->id(),
                'form_data' => $request->form_data ?? [],
                'status' => 'draft'
            ]);

            // Auto-populate fields if requested
            if ($request->auto_populate) {
                $context = array_merge($request->context ?? [], [
                    'tenant_id' => session('tenant_id') ?? auth()->user()->tenant_id ?? 1,
                    'user_id' => auth()->id()
                ]);

                $populatedData = $this->intelligenceService->autoPopulateFields($template, $context);

                if (!empty($populatedData)) {
                    $instance->updateFieldValues($populatedData);
                }
            }

            // Initialize workflow if configured
            if ($template->workflow_configuration) {
                $this->workflowService->initializeWorkflow($instance);
            }

            return $instance;
        });

        return response()->json([
            'data' => new FormInstanceResource($instance->fresh(['template'])),
            'message' => 'Form instance created successfully'
        ], 201);
    }

    /**
     * Update form instance data
     */
    public function update(UpdateFormInstanceRequest $request, FormInstance $instance): JsonResponse
    {
        // $this->authorize('update', $instance);

        // Check if form can still be edited
        if (!$instance->canBeEditedBy(auth()->user())) {
            return response()->json([
                'message' => 'Form cannot be edited in current status'
            ], 422);
        }

        $instance = DB::transaction(function () use ($request, $instance) {
            // Update form data
            if ($request->has('form_data')) {
                $instance->updateFieldValues($request->form_data);
            }

            // Calculate derived fields
            $calculatedFields = $this->intelligenceService->calculateDerivedFields(
                $instance->form_data,
                $instance->template
            );

            if (!empty($calculatedFields)) {
                $instance->update(['calculated_fields' => $calculatedFields]);
            }

            // Update current step if provided
            if ($request->has('current_step')) {
                $instance->update(['current_step' => $request->current_step]);
            }

            return $instance;
        });

        return response()->json([
            'data' => new FormInstanceResource($instance->fresh(['template'])),
            'message' => 'Form instance updated successfully'
        ]);
    }

    /**
     * Submit form instance
     */
    public function submit(Request $request, FormInstance $instance): JsonResponse
    {
        // $this->authorize('update', $instance);

        if ($instance->isSubmitted()) {
            return response()->json([
                'message' => 'Form is already submitted'
            ], 422);
        }

        $result = DB::transaction(function () use ($request, $instance) {
            // Validate form data
            $validation = $this->intelligenceService->validateFormData(
                $instance->form_data,
                $instance->template
            );

            if (!$validation['valid']) {
                return [
                    'success' => false,
                    'message' => 'Form has validation errors',
                    'validation_errors' => $validation['errors']
                ];
            }

            // Execute workflow submission
            if ($instance->template->workflow_configuration && 
                !empty($instance->template->workflow_configuration['steps'])) {
                $workflowResult = $this->workflowService->executeWorkflowStep($instance, 'submit');

                if (!$workflowResult['success']) {
                    return $workflowResult;
                }
            } else {
                // Direct submission without workflow
                $instance->submit($request->submission_notes ?? []);
            }

            // Execute form triggers for form_submitted event
            try {
                $triggerService = app(FormTriggerService::class);
                $triggerResult = $triggerService->executeTriggers($instance, 'form_submitted', [
                    'form_data' => $instance->form_data,
                    'event' => 'form_submitted',
                    'user_id' => auth('api')->id()
                ]);

                // Log trigger execution results
                if ($triggerResult['executed'] > 0) {
                    Log::info('Form triggers executed on submission', [
                        'form_instance_id' => $instance->id,
                        'triggers_executed' => $triggerResult['executed'],
                        'total_triggers' => $triggerResult['total'],
                        'results' => $triggerResult['results']
                    ]);
                }
            } catch (\Exception $e) {
                // Log trigger execution errors but don't fail the submission
                Log::error('Form trigger execution failed on submission', [
                    'form_instance_id' => $instance->id,
                    'error' => $e->getMessage()
                ]);
            }

            return [
                'success' => true,
                'message' => 'Form submitted successfully',
                'instance' => $instance->fresh(['template'])
            ];
        });

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message'],
                'errors' => $result['validation_errors'] ?? null
            ], 422);
        }

        return response()->json([
            'data' => new FormInstanceResource($result['instance']),
            'message' => $result['message']
        ]);
    }

    /**
     * Get form validation results
     */
    public function validate(FormInstance $instance): JsonResponse
    {
        // $this->authorize('view', $instance);

        $validation = $this->intelligenceService->validateFormData(
            $instance->form_data,
            $instance->template
        );

        return response()->json([
            'validation' => $validation,
            'completion_percentage' => $instance->completion_percentage
        ]);
    }

    /**
     * Get field suggestions
     */
    public function fieldSuggestions(Request $request, FormInstance $instance): JsonResponse
    {
        // $this->authorize('view', $instance);

        $request->validate([
            'field_id' => 'required|string',
            'context' => 'array'
        ]);

        $context = array_merge($request->context ?? [], [
            'tenant_id' => $instance->tenant_id,
            'user_id' => $instance->user_id,
            'form_data' => $instance->form_data
        ]);

        $suggestions = $this->intelligenceService->generateFieldSuggestions(
            $request->field_id,
            $context
        );

        return response()->json([
            'field_id' => $request->field_id,
            'suggestions' => $suggestions
        ]);
    }

    /**
     * Get workflow status and available actions
     */
    public function workflow(FormInstance $instance): JsonResponse
    {
        // $this->authorize('view', $instance);

        if (!$instance->template->workflow_configuration) {
            return response()->json([
                'message' => 'No workflow configured for this form'
            ]);
        }

        $workflowStatus = $this->workflowService->determineNextStep($instance);
        $escalations = $this->workflowService->checkEscalation($instance);

        return response()->json([
            'workflow_status' => $workflowStatus,
            'workflow_history' => $instance->workflow_history ?? [],
            'escalations' => $escalations
        ]);
    }

    /**
     * Execute workflow action
     */
    public function workflowAction(Request $request, FormInstance $instance): JsonResponse
    {
        // $this->authorize('workflow', $instance);

        $request->validate([
            'action' => 'required|string|in:approve,reject,request_changes,escalate',
            'notes' => 'nullable|string|max:1000',
            'reason' => 'nullable|string|max:1000',
            'requested_changes' => 'nullable|array'
        ]);

        if (!$instance->template->workflow_configuration) {
            return response()->json([
                'message' => 'No workflow configured for this form'
            ], 422);
        }

        $result = $this->workflowService->executeWorkflowStep($instance, $request->action);

        if (!$result['success']) {
            return response()->json([
                'message' => $result['message']
            ], 422);
        }

        return response()->json([
            'data' => new FormInstanceResource($instance->fresh(['template'])),
            'message' => $result['message']
        ]);
    }

    /**
     * Auto-save form instance
     */
    public function autoSave(Request $request, FormInstance $instance): JsonResponse
    {
        // $this->authorize('update', $instance);

        if (!$instance->canBeEditedBy(auth('api')->user())) {
            return response()->json(['message' => 'Cannot edit form'], 422);
        }

        $request->validate([
            'form_data' => 'required|array'
        ]);

        $instance->updateFieldValues($request->form_data);

        return response()->json([
            'message' => 'Form auto-saved',
            'completion_percentage' => $instance->completion_percentage,
            'timestamp' => now()->toISOString()
        ]);
    }

    /**
     * Delete form instance
     */
    public function destroy(FormInstance $instance): JsonResponse
    {
        // $this->authorize('delete', $instance);

        if ($instance->isSubmitted()) {
            return response()->json([
                'message' => 'Cannot delete submitted form'
            ], 422);
        }

        $instance->delete();

        return response()->json([
            'message' => 'Form instance deleted successfully'
        ]);
    }

    private function getInstanceStats(): array
    {
        $baseQuery = FormInstance::query();

        // Filter by user's own forms if not admin
        if (!auth('api')->user()->hasTenantPermission('forms.view_all')) {
            $baseQuery->where('user_id', auth('api')->id());
        }

        return [
            'total' => $baseQuery->count(),
            'draft' => $baseQuery->where('status', 'draft')->count(),
            'submitted' => $baseQuery->where('status', 'submitted')->count(),
            'approved' => $baseQuery->where('status', 'approved')->count(),
            'rejected' => $baseQuery->where('status', 'rejected')->count()
        ];
    }

    /**
     * Get category-specific statistics
     */
    private function getCategoryStats(string $category): array
    {
        $query = FormInstance::whereHas('template', function($templateQuery) use ($category) {
            $templateQuery->where('category', $category);
        });

        // Filter by user's own forms if not admin
        if (!auth()->user()->hasTenantPermission('forms.view_all')) {
            $query->where('user_id', auth()->id());
        }

        $totalInstances = $query->count();
        $draftCount = (clone $query)->where('status', 'draft')->count();
        $submittedCount = (clone $query)->where('status', 'submitted')->count();
        $approvedCount = (clone $query)->where('status', 'approved')->count();
        $rejectedCount = (clone $query)->where('status', 'rejected')->count();
        $completedCount = (clone $query)->where('status', 'completed')->count();

        // Calculate completion rate
        $completionRate = $totalInstances > 0 ? 
            round((($approvedCount + $completedCount) / $totalInstances) * 100, 2) : 0;

        return [
            'total_instances' => $totalInstances,
            'draft_count' => $draftCount,
            'submitted_count' => $submittedCount,
            'approved_count' => $approvedCount,
            'rejected_count' => $rejectedCount,
            'completed_count' => $completedCount,
            'completion_rate' => $completionRate
        ];
    }

    /**
     * Approve form instance
     */
    public function approve(Request $request, FormInstance $instance): JsonResponse
    {
        // $this->authorize('workflow', $instance);

        $request->validate([
            'notes' => 'nullable|string|max:1000'
        ]);

        // Check if form can be approved
        if (!$instance->isSubmitted()) {
            return response()->json([
                'message' => 'Form must be submitted before it can be approved'
            ], 422);
        }

        if ($instance->isCompleted()) {
            return response()->json([
                'message' => 'Form has already been processed'
            ], 422);
        }

        // Use workflow if configured with steps, otherwise direct approval
        $workflowConfig = $instance->template->workflow_configuration ?? [];
        $hasWorkflowSteps = !empty($workflowConfig['steps'] ?? []) || !empty($workflowConfig['approval_steps'] ?? []);
        
        if ($hasWorkflowSteps) {
            $result = $this->workflowService->executeWorkflowStep($instance, 'approve');
            
            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message']
                ], 422);
            }
        } else {
            // Direct approval
            $instance->approve(auth('api')->id(), $request->input('notes'));
        }

        return response()->json([
            'data' => new FormInstanceResource($instance->fresh(['template'])),
            'message' => 'Form approved successfully'
        ]);
    }

    /**
     * Reject form instance
     */
    public function reject(Request $request, FormInstance $instance): JsonResponse
    {
        // $this->authorize('workflow', $instance);

        $request->validate([
            'reason' => 'required|string|max:1000'
        ]);

        // Check if form can be rejected
        if (!$instance->isSubmitted()) {
            return response()->json([
                'message' => 'Form must be submitted before it can be rejected'
            ], 422);
        }

        if ($instance->isCompleted()) {
            return response()->json([
                'message' => 'Form has already been processed'
            ], 422);
        }

        // Use workflow if configured with steps, otherwise direct rejection
        $workflowConfig = $instance->template->workflow_configuration ?? [];
        $hasWorkflowSteps = !empty($workflowConfig['steps'] ?? []) || !empty($workflowConfig['approval_steps'] ?? []);
        
        if ($hasWorkflowSteps) {
            $result = $this->workflowService->executeWorkflowStep($instance, 'reject');
            
            if (!$result['success']) {
                return response()->json([
                    'message' => $result['message']
                ], 422);
            }
        } else {
            // Direct rejection
            $instance->reject(auth('api')->id(), $request->input('reason'));
        }

        return response()->json([
            'data' => new FormInstanceResource($instance->fresh(['template'])),
            'message' => 'Form rejected successfully'
        ]);
    }
}
