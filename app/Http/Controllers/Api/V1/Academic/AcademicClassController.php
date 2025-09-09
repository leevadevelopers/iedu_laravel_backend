<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\AcademicClass;
use App\Http\Requests\Academic\StoreAcademicClassRequest;
use App\Http\Requests\Academic\UpdateAcademicClassRequest;
use App\Http\Resources\Academic\AcademicClassResource;
use App\Services\V1\Academic\AcademicClassService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AcademicClassController extends Controller
{
    protected AcademicClassService $classService;

    public function __construct(AcademicClassService $classService)
    {
        $this->classService = $classService;
    }

    /**
     * Display a listing of classes
     */
    public function index(Request $request): JsonResponse
    {
        $classes = $this->classService->getClasses($request->all());

        return response()->json([
            'status' => 'success',
            'data' => AcademicClassResource::collection($classes),
            'meta' => [
                'total' => $classes->total(),
                'per_page' => $classes->perPage(),
                'current_page' => $classes->currentPage(),
                'last_page' => $classes->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created class
     */
    public function store(StoreAcademicClassRequest $request): JsonResponse
    {
        try {
            $class = $this->classService->createClass($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Class created successfully',
                'data' => new AcademicClassResource($class)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create class',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified class
     */
    public function show(AcademicClass $class): JsonResponse
    {
        $this->authorize('view', $class);

        return response()->json([
            'status' => 'success',
            'data' => new AcademicClassResource($class->load([
                'subject', 'primaryTeacher', 'students', 'academicYear', 'academicTerm'
            ]))
        ]);
    }

    /**
     * Update the specified class
     */
    public function update(UpdateAcademicClassRequest $request, AcademicClass $class): JsonResponse
    {
        $this->authorize('update', $class);

        try {
            $updatedClass = $this->classService->updateClass($class, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Class updated successfully',
                'data' => new AcademicClassResource($updatedClass)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update class',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified class
     */
    public function destroy(AcademicClass $class): JsonResponse
    {
        $this->authorize('delete', $class);

        try {
            $this->classService->deleteClass($class);

            return response()->json([
                'status' => 'success',
                'message' => 'Class deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete class',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Enroll student in class
     */
    public function enrollStudent(AcademicClass $class, Request $request): JsonResponse
    {
        $this->authorize('update', $class);

        $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        try {
            $enrollment = $this->classService->enrollStudent($class, $request->student_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Student enrolled successfully',
                'data' => $enrollment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to enroll student',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove student from class
     */
    public function removeStudent(AcademicClass $class, Request $request): JsonResponse
    {
        $this->authorize('update', $class);

        $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        try {
            $this->classService->removeStudent($class, $request->student_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Student removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove student',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get class roster
     */
    public function roster(AcademicClass $class): JsonResponse
    {
        $this->authorize('view', $class);

        $roster = $this->classService->getClassRoster($class);

        return response()->json([
            'status' => 'success',
            'data' => $roster
        ]);
    }

    /**
     * Get teacher's classes
     */
    public function teacherClasses(Request $request): JsonResponse
    {
        $teacherId = $request->get('teacher_id', auth()->id());
        $classes = $this->classService->getTeacherClasses($teacherId, $request->all());

        return response()->json([
            'status' => 'success',
            'data' => AcademicClassResource::collection($classes)
        ]);
    }
}
