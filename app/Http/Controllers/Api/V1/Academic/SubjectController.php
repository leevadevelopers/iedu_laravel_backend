<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\Subject;
use App\Http\Requests\Academic\StoreSubjectRequest;
use App\Http\Requests\Academic\UpdateSubjectRequest;
use App\Http\Resources\Academic\SubjectResource;
use App\Services\V1\Academic\SubjectService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    protected SubjectService $subjectService;

    public function __construct(SubjectService $subjectService)
    {
        $this->subjectService = $subjectService;
    }

    /**
     * Display a listing of subjects
     */
    public function index(Request $request): JsonResponse
    {
        if (!auth('api')->user()->hasPermissionTo(['subjects.view', 'academic.view'])) {
            abort(403, 'This action is unauthorized.');
        }

        $subjects = $this->subjectService->getSubjects($request->all());

        return response()->json([
            'status' => 'success',
            'data' => SubjectResource::collection($subjects),
            'meta' => [
                'total' => $subjects->total(),
                'per_page' => $subjects->perPage(),
                'current_page' => $subjects->currentPage(),
                'last_page' => $subjects->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created subject
     */
    public function store(StoreSubjectRequest $request): JsonResponse
    {
        if (!auth('api')->user()->hasPermissionTo(['subjects.create', 'academic.create'])) {
            abort(403, 'This action is unauthorizedsssssss.');
        }

        try {
            $subject = $this->subjectService->createSubject($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Subject created successfully',
                'data' => new SubjectResource($subject)
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
    public function show(Subject $subject): JsonResponse
    {
        if (!auth('api')->user()->hasPermissionTo(['subjects.view', 'academic.view'])) {
            abort(403, 'This action is unauthorized.');
        }

        return response()->json([
            'status' => 'success',
            'data' => new SubjectResource($subject->load(['classes', 'school']))
        ]);
    }

    /**
     * Update the specified subject
     */
    public function update(UpdateSubjectRequest $request, Subject $subject): JsonResponse
    {
        if (!auth('api')->user()->hasPermissionTo(['subjects.edit', 'academic.edit'])) {
            abort(403, 'This action is unauthorized.');
        }

        try {
            $updatedSubject = $this->subjectService->updateSubject($subject, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Subject updated successfully',
                'data' => new SubjectResource($updatedSubject)
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
    public function destroy(Subject $subject): JsonResponse
    {
        if (!auth('api')->user()->hasPermissionTo(['subjects.delete', 'academic.delete'])) {
            abort(403, 'This action is unauthorized.');
        }

        try {
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
    public function byGradeLevel(string $gradeLevel): JsonResponse
    {
        if (!auth('api')->user()->hasPermissionTo(['subjects.view', 'academic.view'])) {
            abort(403, 'This action is unauthorized.');
        }

        $subjects = $this->subjectService->getSubjectsByGradeLevel($gradeLevel);

        return response()->json([
            'status' => 'success',
            'data' => SubjectResource::collection($subjects)
        ]);
    }

    /**
     * Get core subjects
     */
    public function core(): JsonResponse
    {
        if (!auth('api')->user()->hasPermissionTo(['subjects.view', 'academic.view'])) {
            abort(403, 'This action is unauthorized.');
        }

        $subjects = $this->subjectService->getCoreSubjects();

        return response()->json([
            'status' => 'success',
            'data' => SubjectResource::collection($subjects)
        ]);
    }

    /**
     * Get elective subjects
     */
    public function electives(): JsonResponse
    {
        if (!auth('api')->user()->hasPermissionTo(['subjects.view', 'academic.view'])) {
            abort(403, 'This action is unauthorized.');
        }

        $subjects = $this->subjectService->getElectiveSubjects();

        return response()->json([
            'status' => 'success',
            'data' => SubjectResource::collection($subjects)
        ]);
    }
}
