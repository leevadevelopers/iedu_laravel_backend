<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\GradeEntry;
use App\Http\Requests\Academic\StoreGradeEntryRequest;
use App\Http\Requests\Academic\UpdateGradeEntryRequest;
use App\Http\Requests\Academic\BulkGradeEntryRequest;
use App\Services\V1\Academic\GradeEntryService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class GradeEntryController extends Controller
{
    use ApiResponseTrait;
    protected GradeEntryService $gradeEntryService;

    public function __construct(GradeEntryService $gradeEntryService)
    {
        $this->gradeEntryService = $gradeEntryService;
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
     * Display a listing of grade entries
     */
    public function index(Request $request): JsonResponse
    {
        $gradeEntries = $this->gradeEntryService->getGradeEntries($request->all());

        return response()->json([
            'status' => 'success',
            'data' => $gradeEntries->items(),
            'meta' => [
                'total' => $gradeEntries->total(),
                'per_page' => $gradeEntries->perPage(),
                'current_page' => $gradeEntries->currentPage(),
                'last_page' => $gradeEntries->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created grade entry
     */
    public function store(StoreGradeEntryRequest $request): JsonResponse
    {
        $user = auth('api')->user();

        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not authenticated'
            ], 401);
        }

        // Get tenant_id from user if not provided
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant ID is required'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $data = $request->validated();
            $data['tenant_id'] = $tenantId;

            $gradeEntry = $this->gradeEntryService->createGradeEntry($data);

            DB::commit();

            return $this->successResponse($gradeEntry, 'Grade entry created successfully', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create grade entry',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Store bulk grade entries
     */
    public function bulkStore(BulkGradeEntryRequest $request): JsonResponse
    {
        try {
            $results = $this->gradeEntryService->createBulkGradeEntries($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk grade entries processed successfully',
                'data' => [
                    'successful' => $results['successful'],
                    'failed' => $results['failed'],
                    'total' => count($results['successful']) + count($results['failed'])
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process bulk grade entries',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified grade entry
     */
    public function show(GradeEntry $gradeEntry): JsonResponse
    {
        // Verify tenant access
        $tenantId = $this->getCurrentTenantId();
        if (!$tenantId || $gradeEntry->tenant_id != $tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have access to this grade entry'
            ], 403);
        }

        return response()->json([
            'status' => 'success',
            'data' => $gradeEntry->load([
                'student', 'class.subject', 'academicTerm', 'enteredBy', 'modifiedBy'
            ])
        ]);
    }

    /**
     * Update the specified grade entry
     */
    public function update(UpdateGradeEntryRequest $request, GradeEntry $gradeEntry): JsonResponse
    {
        try {
            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $gradeEntry->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this grade entry'
                ], 403);
            }

            DB::beginTransaction();

            $updatedGradeEntry = $this->gradeEntryService->updateGradeEntry($gradeEntry, $request->validated());

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Grade entry updated successfully',
                'data' => $updatedGradeEntry
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update grade entry',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified grade entry
     */
    public function destroy(GradeEntry $gradeEntry): JsonResponse
    {
        try {
            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $gradeEntry->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this grade entry'
                ], 403);
            }

            $this->gradeEntryService->deleteGradeEntry($gradeEntry);

            return response()->json([
                'status' => 'success',
                'message' => 'Grade entry deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete grade entry',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get student grades for a specific term
     */
    public function studentGrades(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_term_id' => 'nullable|exists:academic_terms,id',
        ]);

        $grades = $this->gradeEntryService->getStudentGrades(
            $request->student_id,
            $request->academic_term_id
        );

        return response()->json([
            'success' => true,
            'data' => $grades
        ]);
    }

    /**
     * Get class grades for a specific assessment
     */
    public function classGrades(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'assessment_name' => 'required|string',
        ]);

        $grades = $this->gradeEntryService->getClassGrades(
            $request->class_id,
            $request->assessment_name
        );

        return $this->successPaginatedResponse($grades);
    }

    /**
     * Calculate student GPA
     */
    public function calculateGPA(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
        ]);

        try {
            $gpa = $this->gradeEntryService->calculateStudentGPA(
                $request->student_id,
                $request->academic_term_id
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'student_id' => $request->student_id,
                    'academic_term_id' => $request->academic_term_id,
                    'gpa' => $gpa
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to calculate GPA',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
