<?php

namespace App\Http\Controllers\API\V1\Forms;



use App\Http\Controllers\Controller;
use App\Http\Requests\Forms\CreateFormTemplateRequest;
use App\Http\Requests\Forms\UpdateFormTemplateRequest;
use App\Http\Resources\Forms\FormTemplateResource;
use App\Http\Resources\Forms\FormTemplateCollection;
use App\Models\Forms\FormTemplate;
use App\Services\Forms\FormTemplateService;
// use App\Services\Forms\MethodologyAdapterService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class FormTemplateController extends Controller
{
    protected $templateService;

    public function __construct(
        FormTemplateService $templateService
    ) {
        $this->templateService = $templateService;
    }

    /**
     * List form templates for current tenant
     */
    public function index(Request $request): JsonResponse
    {
        $tenantId = session('tenant_id') ?? auth('api')->user()->tenant_id ?? 1;
        $category = $request->query('category');
        $search = $request->query('search');

        $templates = $this->templateService->getOrgTemplates($tenantId, $category);

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
                'categories' => $this->getAvailableCategories()
            ]
        ]);
    }

    /**
     * Get specific form template
     */
    public function show(FormTemplate $template): JsonResponse
    {
        // $this->authorize('view', $template);

        return response()->json([
            'data' => new FormTemplateResource($template->load(['creator', 'versions']))
        ]);
    }

    /**
     * Create new form template
     */
    public function store(CreateFormTemplateRequest $request): JsonResponse
    {
        $tenantId = $request->tenant_id ?? session('tenant_id') ?? auth('api')->user()->tenant_id ?? 1;

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
        // $this->authorize('update', $template);

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
        // $this->authorize('delete', $template);

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
        // $this->authorize('view', $template);

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
        // $this->authorize('view', $template);

        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000'
        ]);

        $tenantId = session('tenant_id') ?? auth('api')->user()->tenant_id ?? 1;
        $customized = $this->templateService->customizeTemplate(
            $template,
            $request->only(['name', 'description']),
            $tenantId
        );

        return response()->json([
            'data' => new FormTemplateResource($customized),
            'message' => 'Form template customized successfully'
        ], 201);
    }



    /**
     * Get template versions
     */
    public function versions(FormTemplate $template): JsonResponse
    {
        // $this->authorize('view', $template);

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
        // $this->authorize('update', $template);

        $version = $template->versions()->findOrFail($versionId);
        $restoredTemplate = $version->restore();

        return response()->json([
            'data' => new FormTemplateResource($restoredTemplate),
            'message' => "Template restored to version {$version->version_number}"
        ]);
    }

    /**
     * Export form template as JSON
     */
    public function export(FormTemplate $template): JsonResponse
    {
        // $this->authorize('view', $template);

        $exportData = [
            'template_info' => [
                'name' => $template->name,
                'description' => $template->description,
                'version' => $template->version,
                'category' => $template->category,
                'estimated_completion_time' => $template->estimated_completion_time,
                'is_multi_step' => $template->is_multi_step,
                'auto_save' => $template->auto_save,
                'compliance_level' => $template->compliance_level,
                'exported_at' => now()->toISOString(),
                'exported_by' => auth('api')->user()->name,
                'system_version' => '1.0'
            ],
            'form_configuration' => $template->form_configuration,
            'steps' => $template->steps,
            'validation_rules' => $template->validation_rules,
            'workflow_configuration' => $template->workflow_configuration,
            'metadata' => $template->metadata,
            'ai_prompts' => $template->ai_prompts,
            'form_triggers' => $template->form_triggers
        ];

        return response()->json($exportData);
    }

    /**
     * Import form template from JSON
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'template_data' => 'required|array',
            'template_data.template_info' => 'required|array',
            'template_data.template_info.name' => 'required|string|max:255',
            'template_data.steps' => 'required|array',
            'template_data.form_configuration' => 'required|array'
        ]);

        $tenantId = session('tenant_id') ?? auth('api')->user()->tenant_id ?? 1;
        $templateData = $request->template_data;

        // Extract template info
        $templateInfo = $templateData['template_info'];

        // Prepare template data for creation
        $templatePayload = [
            'name' => $templateInfo['name'],
            'description' => $templateInfo['description'] ?? '',
            'version' => $templateInfo['version'] ?? '1.0',
            'category' => $templateInfo['category'] ?? 'custom',
            'estimated_completion_time' => $templateInfo['estimated_completion_time'] ?? '30 minutes',
            'is_multi_step' => $templateInfo['is_multi_step'] ?? false,
            'auto_save' => $templateInfo['auto_save'] ?? true,
            'compliance_level' => $templateInfo['compliance_level'] ?? 'standard',
            'form_configuration' => $templateData['form_configuration'],
            'steps' => $templateData['steps'],
            'validation_rules' => $templateData['validation_rules'] ?? [],
            'workflow_configuration' => $templateData['workflow_configuration'] ?? [],
            'metadata' => $templateData['metadata'] ?? [],
            'ai_prompts' => $templateData['ai_prompts'] ?? null,
            'form_triggers' => $templateData['form_triggers'] ?? null,
            'is_active' => true,
            'is_default' => false
        ];

        // Create the template
        $template = $this->templateService->createTemplate($tenantId, $templatePayload);

        return response()->json([
            'data' => new FormTemplateResource($template),
            'message' => 'Form template imported successfully'
        ], 201);
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

    /**
     * Generate public access token for form template
     */
    public function generatePublicToken(Request $request, FormTemplate $template): JsonResponse
    {
        $user = $request->user();

        // Check if user has permission to generate public tokens
        if (!$user->hasTenantPermission(['forms.admin', 'forms.manage_public_access'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to generate public access tokens. Required permissions: forms.admin or forms.manage_public_access'
            ], 403);
        }

        try {
            $token = $template->generatePublicAccessToken();
            $publicUrl = $template->getPublicAccessUrl();

            return response()->json([
                'data' => [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'public_access_token' => $token,
                    'public_access_url' => $publicUrl,
                    'expires_at' => $template->public_access_expires_at?->toISOString(),
                    'submission_count' => $template->getSubmissionCount(),
                    'can_accept_submissions' => $template->canAcceptSubmissions()
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
     * Revoke public access token for form template
     */
    public function revokePublicToken(Request $request, FormTemplate $template): JsonResponse
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
            $template->revokePublicAccessToken();

            return response()->json([
                'data' => [
                    'template_id' => $template->id,
                    'template_name' => $template->name,
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
     * Update public access settings for form template
     */
    public function updatePublicSettings(Request $request, FormTemplate $template): JsonResponse
    {
        $user = $request->user();

        // Check if user has permission to manage public access
        if (!$user->hasTenantPermission(['forms.admin', 'forms.manage_public_access'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to manage public access settings'
            ], 403);
        }

        $request->validate([
            'allow_multiple_submissions' => 'boolean',
            'max_submissions' => 'nullable|integer|min:1',
            'public_access_settings' => 'nullable|array'
        ]);

        try {
            $template->update([
                'allow_multiple_submissions' => $request->input('allow_multiple_submissions', $template->allow_multiple_submissions),
                'max_submissions' => $request->input('max_submissions'),
                'public_access_settings' => $request->input('public_access_settings', $template->public_access_settings)
            ]);

            return response()->json([
                'data' => new FormTemplateResource($template),
                'message' => 'Public access settings updated successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update public access settings',
                'message' => 'An error occurred while updating the settings'
            ], 500);
        }
    }

    /**
     * Get public submissions for form template
     */
    public function getPublicSubmissions(Request $request, FormTemplate $template): JsonResponse
    {
        $user = $request->user();

        // Check if user has permission to view submissions
        if (!$user->hasTenantPermission(['forms.admin', 'forms.view_submissions'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to view form submissions'
            ], 403);
        }

        $submissions = $template->instances()
            ->where('submission_type', 'public')
            ->where('status', 'submitted')
            ->with(['template'])
            ->orderBy('submitted_at', 'desc')
            ->paginate(20);

        return response()->json([
            'data' => $submissions,
            'meta' => [
                'total_submissions' => $template->getSubmissionCount(),
                'can_accept_submissions' => $template->canAcceptSubmissions()
            ]
        ]);
    }

    /**
     * Get list of deleted templates
     */
    public function deletedList(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has admin permission
        if (!$user->hasTenantPermission(['forms.admin'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to view deleted templates'
            ], 403);
        }

        $tenantId = session('tenant_id') ?? $user->tenant_id ?? 1;
        $category = $request->query('category');

        $deletedTemplates = $this->templateService->getDeletedTemplates($tenantId, $category);

        return response()->json([
            'data' => FormTemplateResource::collection($deletedTemplates),
            'meta' => [
                'total' => $deletedTemplates->count(),
                'categories' => $this->getAvailableCategories()
            ]
        ]);
    }

    /**
     * Get count of deleted templates
     */
    public function deletedCount(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has admin permission
        if (!$user->hasTenantPermission(['forms.admin'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to view deleted templates count'
            ], 403);
        }

        $tenantId = session('tenant_id') ?? $user->tenant_id ?? 1;
        $count = $this->templateService->getDeletedTemplatesCount($tenantId);

        return response()->json([
            'data' => [
                'deleted_count' => $count
            ]
        ]);
    }

    /**
     * Restore a deleted template
     */
    public function restore(Request $request, FormTemplate $template): JsonResponse
    {
        $user = $request->user();

        // Check if user has admin permission
        if (!$user->hasTenantPermission(['forms.admin'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to restore templates'
            ], 403);
        }

        try {
            $restoredTemplate = $this->templateService->restoreTemplate($template->id);

            return response()->json([
                'data' => new FormTemplateResource($restoredTemplate),
                'message' => 'Template restored successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to restore template',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Permanently delete a template (force delete)
     */
    public function forceDelete(Request $request, FormTemplate $template): JsonResponse
    {
        $user = $request->user();

        // Check if user has admin permission
        if (!$user->hasTenantPermission(['forms.admin'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to permanently delete templates'
            ], 403);
        }

        try {
            $deleted = $this->templateService->forceDeleteTemplate($template->id);

            if ($deleted) {
                return response()->json([
                    'message' => 'Template permanently deleted successfully'
                ]);
            } else {
                return response()->json([
                    'error' => 'Failed to delete template',
                    'message' => 'Template could not be permanently deleted'
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to permanently delete template',
                'message' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Debug: Clear all template caches
     */
    public function debugClearCache(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has admin permission
        if (!$user->hasTenantPermission(['forms.admin'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to clear caches'
            ], 403);
        }

        try {
            $this->templateService->clearAllTemplateCaches();

            return response()->json([
                'message' => 'All template caches cleared successfully',
                'timestamp' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to clear caches',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug: Check current template status
     */
    public function debugCheckTemplates(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has admin permission
        if (!$user->hasTenantPermission(['forms.admin'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to check template status'
            ], 403);
        }

        try {
            $tenantId = session('tenant_id') ?? $user->tenant_id ?? 1;

            // Check active templates
            $activeTemplates = FormTemplate::where('tenant_id', $tenantId)
                ->where('is_active', true)
                ->count();

            // Check soft-deleted templates
            $deletedTemplates = FormTemplate::onlyTrashed()
                ->where('tenant_id', $tenantId)
                ->count();

            // Check all templates (including soft-deleted)
            $allTemplates = FormTemplate::withTrashed()
                ->where('tenant_id', $tenantId)
                ->count();

            // Check templates with null deleted_at
            $nullDeletedAt = FormTemplate::withTrashed()
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->count();

            return response()->json([
                'data' => [
                    'tenant_id' => $tenantId,
                    'active_templates' => $activeTemplates,
                    'deleted_templates' => $deletedTemplates,
                    'all_templates' => $allTemplates,
                    'null_deleted_at' => $nullDeletedAt,
                    'cache_status' => 'Check logs for cache operations',
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to check template status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug: Test global scope functionality
     */
    public function debugTestGlobalScope(Request $request): JsonResponse
    {
        $user = $request->user();

        // Check if user has admin permission
        if (!$user->hasTenantPermission(['forms.admin'])) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'You do not have permission to test global scope'
            ], 403);
        }

        try {
            $tenantId = session('tenant_id') ?? $user->tenant_id ?? 1;

            // Test global scope
            $scopeTest = FormTemplate::testGlobalScope();

            // Test with different query approaches
            $directQuery = FormTemplate::where('tenant_id', $tenantId)->count();
            $withNonDeletedScope = FormTemplate::where('tenant_id', $tenantId)->nonDeleted()->count();
            $withActiveScope = FormTemplate::where('tenant_id', $tenantId)->active()->count();

            // Test raw query
            $rawQuery = DB::table('form_templates')
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->where('is_active', true)
                ->count();

            return response()->json([
                'data' => [
                    'tenant_id' => $tenantId,
                    'global_scope_test' => $scopeTest,
                    'direct_query' => $directQuery,
                    'with_non_deleted_scope' => $withNonDeletedScope,
                    'with_active_scope' => $withActiveScope,
                    'raw_query' => $rawQuery,
                    'timestamp' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to test global scope',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
