<?php

namespace App\Http\Controllers\API\V1\Assessment;

use App\Http\Controllers\API\V1\BaseController;
use App\Models\V1\Academic\GradeScale;
use App\Models\V1\Academic\GradeScaleRange;
use App\Services\Assessment\GradeScaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeScaleController extends BaseController
{
    public function __construct(
        protected GradeScaleService $gradeScaleService
    ) {
        $this->middleware('auth:api');
    }

    /**
     * List all grade scales
     */
    public function index(Request $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grade-scales.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $query = GradeScale::with(['ranges', 'gradingSystem']);

        // Filter by grading system
        if ($request->filled('grading_system_id')) {
            $query->where('grading_system_id', $request->grading_system_id);
        }

        // Filter by school
        if ($request->filled('school_id')) {
            $query->where('school_id', $request->school_id);
        }

        // Filter by type
        if ($request->filled('scale_type')) {
            $query->where('scale_type', $request->scale_type);
        }

        // Only default scales
        if ($request->boolean('only_default')) {
            $query->where('is_default', true);
        }

        // Only active systems
        if ($request->boolean('only_active')) {
            $query->active();
        }

        $scales = $query->orderBy('is_default', 'desc')
            ->orderBy('name')
            ->paginate($request->get('per_page', 15));

        return $this->paginatedResponse(
            $scales,
            'Grade scales retrieved successfully'
        );
    }

    /**
     * Create a new grade scale
     */
    public function store(Request $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grade-scales.create')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $validated = $request->validate([
            'grading_system_id' => 'required|exists:grading_systems,id',
            'school_id' => 'nullable|exists:schools,id',
            'name' => 'required|string|max:255',
            'scale_type' => 'required|in:letter,percentage,points,standards',
            'is_default' => 'nullable|boolean',
            'ranges' => 'required|array|min:1',
            'ranges.*.min_value' => 'required|numeric',
            'ranges.*.max_value' => 'required|numeric|gte:ranges.*.min_value',
            'ranges.*.display_label' => 'required|string|max:10',
            'ranges.*.description' => 'nullable|string|max:255',
            'ranges.*.color' => 'nullable|string|max:7',
            'ranges.*.gpa_equivalent' => 'nullable|numeric|min:0|max:4',
            'ranges.*.is_passing' => 'nullable|boolean',
            'ranges.*.order' => 'nullable|integer',
        ]);

        // Validate ranges don't overlap
        $errors = $this->gradeScaleService->validateRanges($validated['ranges']);
        if (!empty($errors)) {
            return $this->errorResponse(is_array($errors) ? implode(', ', $errors) : $errors, 422);
        }

        $gradeScale = $this->gradeScaleService->createGradeScale($validated);

        return $this->successResponse(
            $gradeScale->load('ranges'),
            'Grade scale created successfully',
            201
        );
    }

    /**
     * Show a specific grade scale
     */
    public function show(GradeScale $gradeScale): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grade-scales.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $gradeScale->load(['ranges', 'gradingSystem', 'school']);

        return $this->successResponse(
            $gradeScale,
            'Grade scale retrieved successfully'
        );
    }

    /**
     * Update a grade scale
     */
    public function update(Request $request, GradeScale $gradeScale): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grade-scales.edit')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'scale_type' => 'sometimes|in:letter,percentage,points,standards',
            'is_default' => 'nullable|boolean',
        ]);

        $gradeScale = $this->gradeScaleService->updateGradeScale($gradeScale, $validated);

        return $this->successResponse(
            $gradeScale,
            'Grade scale updated successfully'
        );
    }

    /**
     * Delete a grade scale
     */
    public function destroy(GradeScale $gradeScale): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grade-scales.delete')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        try {
            $this->gradeScaleService->deleteGradeScale($gradeScale);

            return $this->successResponse(
                null,
                'Grade scale deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Add or update a range in a scale
     */
    public function updateRange(Request $request, GradeScale $gradeScale): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grade-scales.edit')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $validated = $request->validate([
            'range_id' => 'nullable|exists:grade_scale_ranges,id',
            'min_value' => 'required|numeric',
            'max_value' => 'required|numeric|gte:min_value',
            'display_label' => 'required|string|max:10',
            'description' => 'nullable|string|max:255',
            'color' => 'nullable|string|max:7',
            'gpa_equivalent' => 'nullable|numeric|min:0|max:4',
            'is_passing' => 'nullable|boolean',
            'order' => 'nullable|integer',
        ]);

        $range = $this->gradeScaleService->updateRange(
            $gradeScale,
            $validated,
            $validated['range_id'] ?? null
        );

        return $this->successResponse(
            $range,
            'Range updated successfully'
        );
    }

    /**
     * Delete a range
     */
    public function deleteRange(GradeScaleRange $range): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grade-scales.delete')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        try {
            $this->gradeScaleService->deleteRange($range);

            return $this->successResponse(
                null,
                'Range deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    /**
     * Get default scale for school
     */
    public function getDefault(Request $request): JsonResponse
    {
        // if (!auth()->user()->can('assessment.grade-scales.view')) {
        //     return $this->errorResponse('Unauthorized', 403);
        // }

        $schoolId = $request->input('school_id') ?? auth()->user()->school_id;
        $gradeScale = $this->gradeScaleService->getDefaultScale($schoolId);

        if (!$gradeScale) {
            return $this->errorResponse('No default grade scale found for this school', 404);
        }

        return $this->successResponse(
            $gradeScale,
            'Default grade scale retrieved successfully'
        );
    }

    /**
     * Convert a score using a scale
     */
    public function convertScore(Request $request, GradeScale $gradeScale): JsonResponse
    {
        $validated = $request->validate([
            'score' => 'required|numeric',
        ]);

        $result = $this->gradeScaleService->convertScore($validated['score'], $gradeScale);

        return $this->successResponse(
            $result,
            'Score converted successfully'
        );
    }

    /**
     * Convert between two scales
     */
    public function convertBetweenScales(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'score' => 'required|numeric',
            'from_scale_id' => 'required|exists:grade_scales,id',
            'to_scale_id' => 'required|exists:grade_scales,id',
        ]);

        $fromScale = GradeScale::with('ranges')->findOrFail($validated['from_scale_id']);
        $toScale = GradeScale::with('ranges')->findOrFail($validated['to_scale_id']);

        $result = $this->gradeScaleService->convertBetweenScales(
            $validated['score'],
            $fromScale,
            $toScale
        );

        return $this->successResponse(
            $result,
            'Score converted between scales successfully'
        );
    }

    /**
     * Calculate GPA
     */
    public function calculateGPA(Request $request, GradeScale $gradeScale): JsonResponse
    {
        $validated = $request->validate([
            'grades' => 'required|array|min:1',
            'grades.*.score' => 'required|numeric',
            'grades.*.weight' => 'nullable|numeric|min:0',
        ]);

        $gpa = $this->gradeScaleService->calculateGPA($validated['grades'], $gradeScale);

        return $this->successResponse(
            [
                'gpa' => $gpa,
                'scale' => $gradeScale->name,
                'grades_count' => count($validated['grades']),
            ],
            'GPA calculated successfully'
        );
    }

    /**
     * Get all scales for a grading system
     */
    public function getByGradingSystem(Request $request, int $gradingSystemId): JsonResponse
    {
        $scales = $this->gradeScaleService->getScalesForSystem($gradingSystemId);

        return $this->successResponse(
            $scales,
            'Grade scales retrieved successfully'
        );
    }
}

