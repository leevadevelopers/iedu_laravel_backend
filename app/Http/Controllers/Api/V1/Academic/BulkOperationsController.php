<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Academic\BulkCreateClassesRequest;
use App\Http\Requests\Academic\BulkEnrollStudentsRequest;
use App\Http\Requests\Academic\BulkImportGradesRequest;
use App\Http\Requests\Academic\BulkGenerateReportCardsRequest;
use App\Services\V1\Academic\BulkOperationsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BulkOperationsController extends Controller
{
    protected BulkOperationsService $bulkOperationsService;

    public function __construct(BulkOperationsService $bulkOperationsService)
    {
        $this->bulkOperationsService = $bulkOperationsService;
    }

    /**
     * Create multiple classes in bulk
     */
    public function createClasses(BulkCreateClassesRequest $request): JsonResponse
    {
        try {
            $result = $this->bulkOperationsService->createClasses($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk class creation completed',
                'data' => $result
            ]);
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
        try {
            $result = $this->bulkOperationsService->enrollStudents($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk student enrollment completed',
                'data' => $result
            ]);
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
    public function createTeachers(Request $request): JsonResponse
    {
        $request->validate([
            'teachers' => 'required|array|min:1',
            'teachers.*.user_id' => 'required|exists:users,id',
            'teachers.*.employee_id' => 'required|string|max:50',
            'teachers.*.first_name' => 'required|string|max:100',
            'teachers.*.last_name' => 'required|string|max:100',
            'teachers.*.hire_date' => 'required|date',
            'teachers.*.employment_type' => 'required|in:full_time,part_time,substitute,contract,volunteer',
            'teachers.*.department' => 'nullable|string|max:100',
            'teachers.*.position' => 'nullable|string|max:100'
        ]);

        try {
            $result = $this->bulkOperationsService->createTeachers($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk teacher creation completed',
                'data' => $result
            ]);
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
    public function createSubjects(Request $request): JsonResponse
    {
        $request->validate([
            'subjects' => 'required|array|min:1',
            'subjects.*.name' => 'required|string|max:255',
            'subjects.*.code' => 'required|string|max:50',
            'subjects.*.subject_area' => 'required|in:mathematics,science,language_arts,social_studies,foreign_language,arts,physical_education,technology,vocational,other',
            'subjects.*.grade_levels' => 'required|array|min:1',
            'subjects.*.grade_levels.*' => 'required|string|in:Pre-K,K,1,2,3,4,5,6,7,8,9,10,11,12',
            'subjects.*.credit_hours' => 'nullable|numeric|min:0.5|max:2.0',
            'subjects.*.is_core_subject' => 'nullable|boolean',
            'subjects.*.is_elective' => 'nullable|boolean'
        ]);

        try {
            $result = $this->bulkOperationsService->createSubjects($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk subject creation completed',
                'data' => $result
            ]);
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
            'transfers.*.from_class_id' => 'required|exists:academic_classes,id',
            'transfers.*.to_class_id' => 'required|exists:academic_classes,id',
            'transfers.*.effective_date' => 'required|date|after_or_equal:today',
            'transfers.*.reason' => 'nullable|string|max:500'
        ]);

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
