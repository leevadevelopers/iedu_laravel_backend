<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\Subject;
use App\Http\Requests\Academic\StoreSubjectRequest;
use App\Http\Requests\Academic\UpdateSubjectRequest;
use App\Services\V1\Academic\SubjectService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class SubjectController extends Controller
{
    use ApiResponseTrait;
    protected SubjectService $subjectService;

    public function __construct(SubjectService $subjectService)
    {
        $this->subjectService = $subjectService;
        $this->middleware('permission:academic.subjects.view')->only(['index', 'show', 'core', 'electives', 'byGradeLevel']);
        $this->middleware('permission:academic.subjects.create')->only(['store']);
        $this->middleware('permission:academic.subjects.edit')->only(['update']);
        $this->middleware('permission:academic.subjects.delete')->only(['destroy']);
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
     * Display a listing of subjects
     */
    public function index(Request $request): JsonResponse
    {

        $subjects = $this->subjectService->getSubjects($request->all());

        return $this->successPaginatedResponse($subjects);
    }

    /**
     * Store a newly created subject
     */
    public function store(StoreSubjectRequest $request): JsonResponse
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

            $subject = $this->subjectService->createSubject($data);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Subject created successfully',
                'data' => $subject
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create subject',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified subject
     */
    public function show(Request $request, $id): JsonResponse
    {
        try {
            $subject = $this->findSubject($id);

            if (!$subject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subject not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $subject->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this subject'
                ], 403);
            }

            return response()->json([
                'status' => 'success',
                'data' => $subject->load(['classes', 'school'])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve subject',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified subject
     */
    public function update(UpdateSubjectRequest $request, $id): JsonResponse
    {
        try {
            $subject = $this->findSubject($id);

            if (!$subject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subject not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $subject->tenant_id != $tenantId) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this subject'
                ], 403);
            }

            DB::beginTransaction();

            $updatedSubject = $this->subjectService->updateSubject($subject, $request->validated());

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Subject updated successfully',
                'data' => $updatedSubject
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update subject',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified subject
     */
    public function destroy($id): JsonResponse
    {
        try {
            DB::beginTransaction();

            $subject = $this->findSubject($id);

            if (!$subject) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subject not found'
                ], 404);
            }

            // Verify tenant access
            $tenantId = $this->getCurrentTenantId();
            if (!$tenantId || $subject->tenant_id != $tenantId) {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'You do not have access to this subject'
                ], 403);
            }

            $this->subjectService->deleteSubject($subject);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Subject archived successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to archive subject',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get subjects by grade level
     */
    public function byGradeLevel(Request $request, string $gradeLevel): JsonResponse
    {
        $schoolId = $this->getCurrentSchoolId();

        $subjects = $this->subjectService->getSubjectsByGradeLevel($gradeLevel, $schoolId);

        return response()->json([
            'status' => 'success',
            'data' => $subjects
        ]);
    }

    /**
     * Get core subjects
     */
    public function core(Request $request): JsonResponse
    {
        $schoolId = $this->getCurrentSchoolId();

        $subjects = $this->subjectService->getCoreSubjects($schoolId);

        return response()->json([
            'status' => 'success',
            'data' => $subjects
        ]);
    }

    /**
     * Get elective subjects
     */
    public function electives(Request $request): JsonResponse
    {
        $schoolId = $this->getCurrentSchoolId();

        $subjects = $this->subjectService->getElectiveSubjects($schoolId);

        return response()->json([
            'status' => 'success',
            'data' => $subjects
        ]);
    }

    /**
     * Find subject with proper scoping
     */
    private function findSubject($id): ?Subject
    {
        // Simplified approach - find subject by ID and tenant_id only
        $user = Auth::user();

        return Subject::where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->first();
    }
}
