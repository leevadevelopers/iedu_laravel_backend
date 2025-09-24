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
     * Display a listing of subjects
     */
    public function index(Request $request): JsonResponse
    {
        // Validate school_id is provided
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id'
        ]);

        $subjects = $this->subjectService->getSubjects($request->all());

        return $this->successPaginatedResponse($subjects);
    }

    /**
     * Store a newly created subject
     */
    public function store(StoreSubjectRequest $request): JsonResponse
    {
        try {
            $subject = $this->subjectService->createSubject($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Subject created successfully',
                'data' => $subject
            ], 201);
        } catch (\Exception $e) {
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

            $updatedSubject = $this->subjectService->updateSubject($subject, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Subject updated successfully',
                'data' => $updatedSubject
            ]);
        } catch (\Exception $e) {
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
            $subject = $this->findSubject($id);

            if (!$subject) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subject not found'
                ], 404);
            }

            $this->subjectService->deleteSubject($subject);

            return response()->json([
                'status' => 'success',
                'message' => 'Subject archived successfully'
            ]);
        } catch (\Exception $e) {
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
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id'
        ]);

        $subjects = $this->subjectService->getSubjectsByGradeLevel($gradeLevel, $request->school_id);

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
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id'
        ]);

        $subjects = $this->subjectService->getCoreSubjects($request->school_id);

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
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id'
        ]);

        $subjects = $this->subjectService->getElectiveSubjects($request->school_id);

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
        $user = Auth::user();

        // Get current school ID
        $schoolUsers = $user->activeSchools();
        if ($schoolUsers->isEmpty()) {
            return null;
        }

        $schoolId = $schoolUsers->first()->school_id;

        // Find subject with proper scoping
        return Subject::where('id', $id)
            ->where('tenant_id', $user->tenant_id)
            ->where('school_id', $schoolId)
            ->first();
    }
}
