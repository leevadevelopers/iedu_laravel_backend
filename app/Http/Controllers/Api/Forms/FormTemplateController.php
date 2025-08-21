<?php

namespace App\Http\Controllers\Api\Forms;



use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\CreateFormTemplateRequest;
use App\Http\Requests\Forms\UpdateFormTemplateRequest;
use App\Http\Resources\Forms\FormTemplateResource;
use App\Http\Resources\Forms\FormTemplateCollection;
use App\Models\Forms\FormTemplate;
use App\Services\Forms\FormTemplateService;
use App\Services\Forms\MethodologyAdapterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FormTemplateController extends Controller
{
    protected $templateService;
    protected $methodologyAdapter;

    public function __construct(
        FormTemplateService $templateService,
        MethodologyAdapterService $methodologyAdapter
    ) {
        $this->templateService = $templateService;
        $this->methodologyAdapter = $methodologyAdapter;
    }

    /**
     * List form templates for current tenant
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = session('tenant_id');
        $category = $request->query('category');
        $methodology = $request->query('methodology');
        $search = $request->query('search');

        $templates = $this->templateService->getOrgTemplates($tenantId, $category, $methodology);

        if ($search) {
            $templates = $templates->filter(function ($template) use ($search) {
                return str_contains(strtolower($template->name), strtolower($search)) ||
                       str_contains(strtolower($template->description ?? ''), strtolower($search));
            });
        }

        return response()->json([
            'data' => FormTemplateResource::collection($templates),
            'meta' => [
                'total' => $templates->count(),
                'categories' => $this->getAvailableCategories(),
                'methodologies' => $this->getAvailableMethodologies()
            ]
        ]);
    }

    /**
     * Get specific form template
     */
    public function show(FormTemplate $template): JsonResponse
    {
        $this->authorize('view', $template);

        return response()->json([
            'data' => new FormTemplateResource($template->load(['creator', 'versions']))
        ]);
    }

    /**
     * Create new form template
     */
    public function store(CreateFormTemplateRequest $request): JsonResponse
    {
        $tenantId = session('tenant_id');
        
        $template = $this->templateService->createTemplate($tenantId, $request->validated());

        return response()->json([
            'data' => new FormTemplateResource($template),
            'message' => 'Form template created successfully'
        ], 201);
    }

    /**
     * Update form template
     */
    public function update(UpdateFormTemplateRequest $request, FormTemplate $template): JsonResponse
    {
        $this->authorize('update', $template);

        $updatedTemplate = $this->templateService->updateTemplate($template, $request->validated());

        return response()->json([
            'data' => new FormTemplateResource($updatedTemplate),
            'message' => 'Form template updated successfully'
        ]);
    }

    /**
     * Delete form template
     */
    public function destroy(FormTemplate $template): JsonResponse
    {
        $this->authorize('delete', $template);

        // Check if template is in use
        if ($template->instances()->exists()) {
            return response()->json([
                'message' => 'Cannot delete template that has form instances'
            ], 422);
        }

        $template->delete();

        return response()->json([
            'message' => 'Form template deleted successfully'
        ]);
    }

    /**
     * Duplicate form template
     */
    public function duplicate(Request $request, FormTemplate $template): JsonResponse
    {
        $this->authorize('view', $template);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000'
        ]);

        $duplicated = $this->templateService->duplicateTemplate($template, [
            'name' => $request->name,
            'description' => $request->description
        ]);

        return response()->json([
            'data' => new FormTemplateResource($duplicated),
            'message' => 'Form template duplicated successfully'
        ], 201);
    }

    /**
     * Customize template for current organization
     */
    public function customize(Request $request, FormTemplate $template): JsonResponse
    {
        $this->authorize('view', $template);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'methodology_type' => 'nullable|string|in:universal,usaid,world_bank,eu,custom'
        ]);

        $tenantId = session('tenant_id');
        $customized = $this->templateService->customizeTemplate(
            $template, 
            $request->only(['name', 'description', 'methodology_type']), 
            $tenantId
        );

        return response()->json([
            'data' => new FormTemplateResource($customized),
            'message' => 'Form template customized successfully'
        ], 201);
    }

    /**
     * Get methodology requirements
     */
    public function methodologyRequirements(string $methodology): JsonResponse
    {
        $requirements = $this->methodologyAdapter->getMethodologyRequirements($methodology);
        $complianceConfig = $this->methodologyAdapter->getComplianceConfiguration($methodology);

        return response()->json([
            'methodology' => $methodology,
            'requirements' => $requirements,
            'compliance_configuration' => $complianceConfig
        ]);
    }

    /**
     * Preview template adaptation
     */
    public function previewAdaptation(Request $request): JsonResponse
    {
        $request->validate([
            'template_data' => 'required|array',
            'methodology' => 'required|string|in:usaid,world_bank,eu'
        ]);

        $adaptedTemplate = $this->methodologyAdapter->adaptTemplate(
            $request->template_data,
            $request->methodology
        );

        return response()->json([
            'adapted_template' => $adaptedTemplate,
            'changes_summary' => $this->generateAdaptationSummary($request->template_data, $adaptedTemplate)
        ]);
    }

    /**
     * Get template versions
     */
    public function versions(FormTemplate $template): JsonResponse
    {
        $this->authorize('view', $template);

        $versions = $template->versions()->with('creator')->latest()->get();

        return response()->json([
            'data' => $versions->map(function ($version) {
                return [
                    'id' => $version->id,
                    'version_number' => $version->version_number,
                    'changes_summary' => $version->changes_summary,
                    'created_by' => $version->creator->name,
                    'created_at' => $version->created_at->toISOString()
                ];
            })
        ]);
    }

    /**
     * Restore template from version
     */
    public function restoreVersion(FormTemplate $template, int $versionId): JsonResponse
    {
        $this->authorize('update', $template);

        $version = $template->versions()->findOrFail($versionId);
        $restoredTemplate = $version->restore();

        return response()->json([
            'data' => new FormTemplateResource($restoredTemplate),
            'message' => "Template restored to version {$version->version_number}"
        ]);
    }

    private function getAvailableCategories(): array
    {
        return [
            'project_creation' => 'Project Creation',
            'contract_management' => 'Contract Management',
            'procurement' => 'Procurement',
            'monitoring' => 'Monitoring & Evaluation',
            'financial' => 'Financial Management',
            'custom' => 'Custom Forms'
        ];
    }

    private function getAvailableMethodologies(): array
    {
        return [
            'universal' => 'Universal',
            'usaid' => 'USAID',
            'world_bank' => 'World Bank',
            'eu' => 'European Union',
            'custom' => 'Custom'
        ];
    }

    private function generateAdaptationSummary(array $original, array $adapted): array
    {
        $changes = [];

        // Compare steps count
        $originalSteps = count($original['steps'] ?? []);
        $adaptedSteps = count($adapted['steps'] ?? []);
        
        if ($originalSteps !== $adaptedSteps) {
            $changes[] = "Steps changed from {$originalSteps} to {$adaptedSteps}";
        }

        // Check for new validation rules
        $originalRules = count($original['validation_rules'] ?? []);
        $adaptedRules = count($adapted['validation_rules'] ?? []);
        
        if ($originalRules !== $adaptedRules) {
            $changes[] = "Validation rules added: " . ($adaptedRules - $originalRules);
        }

        // Check workflow changes
        if (isset($adapted['workflow_configuration']) && !isset($original['workflow_configuration'])) {
            $changes[] = "Workflow configuration added";
        }

        return $changes;
    }
}
