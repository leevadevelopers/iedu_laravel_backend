<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\AcademicClass;
use App\Http\Requests\Academic\StoreAcademicClassRequest;
use App\Http\Requests\Academic\UpdateAcademicClassRequest;
use App\Services\V1\Academic\AcademicClassService;
use App\Traits\ApiResponseTrait;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class AcademicClassController extends Controller
{
    use ApiResponseTrait;
    protected AcademicClassService $classService;

    public function __construct(AcademicClassService $classService)
    {
        $this->classService = $classService;
        $this->middleware('permission:academic.classes.view')->only(['index', 'show', 'roster', 'teacherClasses']);
        $this->middleware('permission:academic.classes.create')->only(['store']);
        $this->middleware('permission:academic.classes.edit')->only(['update']);
        $this->middleware('permission:academic.classes.delete')->only(['destroy']);
        $this->middleware('permission:academic.classes.enroll')->only(['enrollStudent', 'removeStudent']);
    }

    /**
     * Display a listing of classes
     */
    public function index(Request $request): JsonResponse
    {
        // Validate school_id is provided
        $request->validate([
            'school_id' => 'required|integer|exists:schools,id'
        ]);

        $classes = $this->classService->getClasses($request->all());

        return response()->json([
            'status' => 'success',
            'data' => $classes->items(),
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

            return $this->successResponse($class, 'Class created successfully', 201);
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
    public function show(Request $request, $id): JsonResponse
    {
        try {
            // Find class explicitly to avoid model binding issues with TenantScope
            $class = $this->classService->getClassById($id);

            if (!$class) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $class->load([
                    'subject', 'primaryTeacher', 'students', 'academicYear', 'academicTerm'
                ])
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve class',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update the specified class
     */
    public function update(UpdateAcademicClassRequest $request, $id): JsonResponse
    {
        try {
            $class = $this->classService->getClassById($id);

            if (!$class) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

            $updatedClass = $this->classService->updateClass($class, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Class updated successfully',
                'data' => $updatedClass
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
    public function destroy($id): JsonResponse
    {
        try {
            $class = $this->classService->getClassById($id);

            if (!$class) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

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
    public function enrollStudent($id, Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        try {
            $class = $this->classService->getClassById($id);

            if (!$class) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

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
    public function removeStudent($id, Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        try {
            $class = $this->classService->getClassById($id);

            if (!$class) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

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
    public function roster($id): JsonResponse
    {
        try {
            $class = $this->classService->getClassById($id);

            if (!$class) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Class not found'
                ], 404);
            }

            $roster = $this->classService->getClassRoster($class);

            return $this->successResponse($roster);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get class roster',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get teacher's classes
     */
    public function teacherClasses(Request $request): JsonResponse
    {
        $teacherId = $request->get('teacher_id', Auth::id());
        $classes = $this->classService->getTeacherClasses($teacherId, $request->all());

        return $this->successResponse($classes);
    }
}
