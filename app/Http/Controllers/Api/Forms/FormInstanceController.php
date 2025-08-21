<?php

namespace App\Http\Controllers\Api\Forms;

use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\CreateFormInstanceRequest;
use App\Http\Requests\Forms\UpdateFormInstanceRequest;
use App\Http\Resources\Forms\FormInstanceResource;
use App\Http\Resources\Forms\FormInstanceCollection;
use App\Models\Forms\FormInstance;
use App\Models\Forms\FormTemplate;
use App\Services\Forms\FormIntelligenceService;
use App\Services\Forms\WorkflowIntegrationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

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
            ->latest();

        // Filter by user's own forms if not admin
        if (!auth()->user()->hasTenantPermission('forms.view_all')) {
            $query->where('user_id', auth()->id());
        }

        $instances = $query->paginate($request->per_page ?? 20);

        return response()->json([
            'data' => FormInstanceResource::collection($instances),
            'meta' => [
                'pagination' => [
                    'current_page' => $instances->currentPage(),
                    'per_page' => $instances->perPage(),
                    'total' => $instances->total(),
                    'last_page' => $instances->lastPage()
                ],
                'stats' => $this->getInstanceStats()
            ]
        ]);
    }

    /**
     * Get specific form instance
     */
    public function show(FormInstance $instance): JsonResponse
    {
        $this->authorize('view', $instance);

        return response()->json([
            'data' => new FormInstanceResource($instance->load(['template', 'user', 'submissions']))
        ]);
    }

    /**
     * Create new form instance from template
     */
    public function store(CreateFormInstanceRequest $request): JsonResponse
    {
        $template = FormTemplate::findOrFail($request->form_template_id);
        
        $this->authorize('create', [FormInstance::class, $template]);

        $instance = DB::transaction(function () use ($request, $template) {
            // Create form instance
            $instance = FormInstance::create([
                'tenant_id' => session('tenant_id'),
                'form_template_id' => $template->id,
                'user_id' => auth()->id(),
                'form_data' => $request->form_data ?? [],
                'status' => 'draft'
            ]);

            // Auto-populate fields if requested
            if ($request->auto_populate) {
                $context = array_merge($request->context ?? [], [
                    'tenant_id' => session('tenant_id'),
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
        $this->authorize('update', $instance);

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
        $this->authorize('update', $instance);

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
            if ($instance->template->workflow_configuration) {
                $workflowResult = $this->workflowService->executeWorkflowStep($instance, 'submit');
                
                if (!$workflowResult['success']) {
                    return $workflowResult;
                }
            } else {
                // Direct submission without workflow
                $instance->submit($request->submission_notes ?? []);
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
        $this->authorize('view', $instance);

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
        $this->authorize('view', $instance);

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
        $this->authorize('view', $instance);

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
        $this->authorize('workflow', $instance);

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
        $this->authorize('update', $instance);

        if (!$instance->canBeEditedBy(auth()->user())) {
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
        $this->authorize('delete', $instance);

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
        if (!auth()->user()->hasTenantPermission('forms.view_all')) {
            $baseQuery->where('user_id', auth()->id());
        }

        return [
            'total' => $baseQuery->count(),
            'draft' => $baseQuery->where('status', 'draft')->count(),
            'submitted' => $baseQuery->where('status', 'submitted')->count(),
            'approved' => $baseQuery->where('status', 'approved')->count(),
            'rejected' => $baseQuery->where('status', 'rejected')->count()
        ];
    }
}