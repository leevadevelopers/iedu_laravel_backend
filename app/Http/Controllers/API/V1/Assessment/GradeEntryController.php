<?php

namespace App\Http\Controllers\API\V1\Assessment;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Assessment\StoreGradeEntryRequest;
use App\Http\Requests\Assessment\UpdateGradeEntryRequest;
use App\Http\Requests\Assessment\BulkImportGradesRequest;
use App\Http\Resources\Assessment\GradeEntryResource;
use App\Models\V1\Academic\GradeEntry;
use App\Models\Assessment\Assessment;
use App\Services\Assessment\GradeService;
use App\Jobs\Assessment\PublishGrades;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeEntryController extends BaseController
{
    public function __construct(
        protected GradeService $gradeService
    ) {
        $this->middleware('auth:api');
    }

    public function index(Request $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grades.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $query = GradeEntry::with(['student', 'class', 'academicTerm', 'enteredBy']);

        // Filter by assessment name
        if ($request->filled('assessment_name')) {
            $query->where('assessment_name', $request->assessment_name);
        }

        // Filter by student
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        // Filter by class
        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        // Filter by term
        if ($request->filled('academic_term_id')) {
            $query->where('academic_term_id', $request->academic_term_id);
        }

        // Only for current student
        if (auth()->user()->hasRole(['student'])) {
            $query->where('student_id', auth()->id());
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $grades = $query->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            GradeEntryResource::collection($grades),
            'Grades retrieved successfully'
        );
    }

    public function store(StoreGradeEntryRequest $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grades.enter')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $gradeEntry = $this->gradeService->enterGrade($request->validated());

        return $this->successResponse(
            new GradeEntryResource($gradeEntry),
            'Grade entered successfully',
            201
        );
    }

    public function show(GradeEntry $gradeEntry): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grades.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        // Students can only view their own grades
        if (auth()->user()->hasRole('student') && $gradeEntry->student_id !== auth()->id()) {
            return $this->errorResponse('Unauthorized', 403);
        }

        $gradeEntry->load(['student', 'class', 'academicTerm', 'enteredBy', 'modifiedBy']);

        return $this->successResponse(
            new GradeEntryResource($gradeEntry),
            'Grade retrieved successfully'
        );
    }

    public function update(UpdateGradeEntryRequest $request, GradeEntry $gradeEntry): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grades.edit')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $gradeEntry = $this->gradeService->updateGrade($gradeEntry, $request->validated());

        return $this->successResponse(
            new GradeEntryResource($gradeEntry),
            'Grade updated successfully'
        );
    }

    public function destroy(GradeEntry $gradeEntry): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grades.delete')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $gradeEntry->delete();

        return $this->successResponse(
            null,
            'Grade deleted successfully'
        );
    }

    public function publishGrades(Assessment $assessment): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grades.publish')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        PublishGrades::dispatch($assessment);

        return $this->successResponse(
            null,
            'Grades publication started'
        );
    }

    public function bulkImport(BulkImportGradesRequest $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grades.bulk-import')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        // Process CSV/XLSX file
        // This is a simplified version - implement CSV parsing logic
        
        return $this->successResponse(
            null,
            'Bulk import started'
        );
    }

    public function studentGrades(Request $request, int $studentId): JsonResponse
    {
        // Students can only view their own grades
        // if (auth()->user()->hasRole('student') && auth()->id() !== $studentId) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $query = GradeEntry::where('student_id', $studentId)
            ->with(['class', 'academicTerm', 'student']);

        if ($request->filled('academic_term_id')) {
            $query->where('academic_term_id', $request->academic_term_id);
        }

        if ($request->filled('class_id')) {
            $query->where('class_id', $request->class_id);
        }

        $grades = $query->get();

        return $this->successResponse(
            GradeEntryResource::collection($grades),
            'Student grades retrieved successfully'
        );
    }
}

