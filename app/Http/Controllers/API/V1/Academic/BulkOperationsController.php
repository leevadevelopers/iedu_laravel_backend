<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\BulkCreateClassesRequest;
use App\Http\Requests\Academic\BulkEnrollStudentsRequest;
use App\Http\Requests\Academic\BulkImportGradesRequest;
use App\Http\Requests\Academic\BulkGenerateReportCardsRequest;
use App\Http\Requests\Academic\BulkCreateTeachersRequest;
use App\Http\Requests\Academic\BulkCreateSubjectsRequest;
use App\Services\V1\Academic\BulkOperationsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class BulkOperationsController extends Controller
{
    protected BulkOperationsService $bulkOperationsService;

    public function __construct(BulkOperationsService $bulkOperationsService)
    {
        $this->bulkOperationsService = $bulkOperationsService;
    }

    /**
     * Get the current school ID from authenticated user
     */
    protected function getCurrentSchoolId(): ?int
    {
        $user = auth('api')->user();

        if (!$user) {
            return null;
        }

        // Try getCurrentSchool method first (preferred)
        if (method_exists($user, 'getCurrentSchool')) {
            $currentSchool = $user->getCurrentSchool();
            if ($currentSchool) {
                return $currentSchool->id;
            }
        }

        // Fallback to school_id attribute
        if (isset($user->school_id) && $user->school_id) {
            return $user->school_id;
        }

        // Try activeSchools relationship
        if (method_exists($user, 'activeSchools')) {
            $activeSchools = $user->activeSchools();
            if ($activeSchools && $activeSchools->count() > 0) {
                $firstSchool = $activeSchools->first();
                if ($firstSchool && isset($firstSchool->school_id)) {
                    return $firstSchool->school_id;
                }
            }
        }

        return null;
    }

    /**
     * Get the current tenant ID from authenticated user
     */
    protected function getCurrentTenantId(): ?int
    {
        $user = auth('api')->user();

        if (!$user) {
            return null;
        }

        // Try tenant_id attribute first
        if (isset($user->tenant_id) && $user->tenant_id) {
            return $user->tenant_id;
        }

        // Try getCurrentTenant method
        if (method_exists($user, 'getCurrentTenant')) {
            $currentTenant = $user->getCurrentTenant();
            if ($currentTenant) {
                return $currentTenant->id;
            }
        }

        return null;
    }

    /**
     * Create multiple classes in bulk
     */
    public function createClasses(BulkCreateClassesRequest $request): JsonResponse
    {
        // Verify tenant access
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant ID is required'
            ], 422);
        }

        try {
            $result = $this->bulkOperationsService->createClasses($request->validated());

            return response()->json([
                'status' => $result['success'] ? 'success' : 'partial_success',
                'message' => $result['success']
                    ? 'Bulk class creation completed'
                    : 'Bulk class creation completed with some errors',
                'data' => $result
            ], $result['success'] ? 200 : 207);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create classes in bulk',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Enroll multiple students in bulk
     */
    public function enrollStudents(BulkEnrollStudentsRequest $request): JsonResponse
    {
        // Verify tenant access
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant ID is required'
            ], 422);
        }

        try {
            $result = $this->bulkOperationsService->enrollStudents($request->validated());

            return response()->json([
                'status' => $result['success'] ? 'success' : 'partial_success',
                'message' => $result['success']
                    ? 'Bulk student enrollment completed'
                    : 'Bulk student enrollment completed with some errors',
                'data' => $result
            ], $result['success'] ? 200 : 207);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to enroll students in bulk',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Import grades in bulk
     */
    public function importGrades(BulkImportGradesRequest $request): JsonResponse
    {
        // Verify tenant access
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant ID is required'
            ], 422);
        }

        try {
            $result = $this->bulkOperationsService->importGrades($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk grade import completed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to import grades in bulk',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Generate report cards in bulk
     */
    public function generateReportCards(BulkGenerateReportCardsRequest $request): JsonResponse
    {
        // Verify tenant access
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant ID is required'
            ], 422);
        }

        try {
            $result = $this->bulkOperationsService->generateReportCards($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk report card generation completed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to generate report cards in bulk',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Bulk update student information
     */
    public function updateStudents(Request $request): JsonResponse
    {
        $request->validate([
            'students' => 'required|array|min:1',
            'students.*.id' => 'required|exists:students,id',
            'students.*.data' => 'required|array',
            'update_type' => 'required|in:personal_info,academic_info,contact_info,all'
        ]);

        // Verify tenant access
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant ID is required'
            ], 422);
        }

        try {
            $result = $this->bulkOperationsService->updateStudents($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk student update completed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update students in bulk',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Bulk create teachers
     */
    public function createTeachers(BulkCreateTeachersRequest $request): JsonResponse
    {
        // Verify tenant access
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant ID is required'
            ], 422);
        }

        try {
            $result = $this->bulkOperationsService->createTeachers($request->validated());

            return response()->json([
                'status' => $result['success'] ? 'success' : 'partial_success',
                'message' => $result['success']
                    ? 'Bulk teacher creation completed'
                    : 'Bulk teacher creation completed with some errors',
                'data' => $result
            ], $result['success'] ? 200 : 207);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create teachers in bulk',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Bulk create subjects
     */
    public function createSubjects(BulkCreateSubjectsRequest $request): JsonResponse
    {
        // Verify tenant access
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant ID is required'
            ], 422);
        }

        try {
            $result = $this->bulkOperationsService->createSubjects($request->validated());

            return response()->json([
                'status' => $result['success'] ? 'success' : 'partial_success',
                'message' => $result['success']
                    ? 'Bulk subject creation completed'
                    : 'Bulk subject creation completed with some errors',
                'data' => $result
            ], $result['success'] ? 200 : 207);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create subjects in bulk',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Bulk transfer students between classes
     */
    public function transferStudents(Request $request): JsonResponse
    {
        $request->validate([
            'transfers' => 'required|array|min:1',
            'transfers.*.student_id' => 'required|exists:students,id',
            'transfers.*.from_class_id' => 'required|exists:classes,id',
            'transfers.*.to_class_id' => 'required|exists:classes,id',
            'transfers.*.effective_date' => 'required|date|after_or_equal:today',
            'transfers.*.reason' => 'nullable|string|max:500'
        ]);

        // Verify tenant access
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant ID is required'
            ], 422);
        }

        try {
            $result = $this->bulkOperationsService->transferStudents($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk student transfer completed',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to transfer students in bulk',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get bulk operation status
     */
    public function getOperationStatus(string $operationId): JsonResponse
    {
        try {
            $status = $this->bulkOperationsService->getOperationStatus($operationId);

            return response()->json([
                'status' => 'success',
                'data' => $status
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get operation status',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * Cancel bulk operation
     */
    public function cancelOperation(string $operationId): JsonResponse
    {
        try {
            $this->bulkOperationsService->cancelOperation($operationId);

            return response()->json([
                'status' => 'success',
                'message' => 'Operation cancelled successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel operation',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
