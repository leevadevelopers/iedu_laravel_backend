<?php

namespace App\Http\Controllers\API\V1\Schedule;

use App\Http\Controllers\Controller;
use App\Http\Requests\V1\Schedule\PublishLessonPlanRequest;
use App\Http\Requests\V1\Schedule\ShareLessonPlanRequest;
use App\Http\Requests\V1\Schedule\StoreLessonPlanRequest;
use App\Http\Requests\V1\Schedule\UpdateLessonPlanRequest;
use App\Http\Resources\V1\Schedule\LessonPlanResource;
use App\Models\V1\Schedule\LessonPlan;
use App\Services\V1\Schedule\LessonPlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonPlanController extends Controller
{
    public function __construct(private LessonPlanService $lessonPlanService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $plans = $this->lessonPlanService->getWithFilters($request->all());

        return response()->json([
            'data' => LessonPlanResource::collection($plans),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

    public function week(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'week_start' => 'required|date',
            'class_id' => 'nullable|integer',
        ]);

        $plans = $this->lessonPlanService->getWeekView($validated['week_start'], $validated['class_id'] ?? null);

        return response()->json([
            'data' => LessonPlanResource::collection($plans),
        ]);
    }

    public function calendar(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $plans = $this->lessonPlanService->getCalendar($validated);

        return response()->json([
            'data' => LessonPlanResource::collection($plans),
        ]);
    }

    public function library(Request $request): JsonResponse
    {
        $plans = $this->lessonPlanService->getLibrary($request->all());

        return response()->json([
            'data' => LessonPlanResource::collection($plans),
            'meta' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
            ],
        ]);
    }

    public function store(StoreLessonPlanRequest $request): JsonResponse
    {
        $plan = $this->lessonPlanService->createDraft($request->validated());

        return response()->json([
            'message' => 'Lesson plan created',
            'data' => new LessonPlanResource($plan),
        ], 201);
    }

    public function show(LessonPlan $lessonPlan): JsonResponse
    {
        return response()->json([
            'data' => new LessonPlanResource($lessonPlan->load(['subject', 'class', 'teacher'])),
        ]);
    }

    public function update(UpdateLessonPlanRequest $request, LessonPlan $lessonPlan): JsonResponse
    {
        $plan = $this->lessonPlanService->updatePlan($lessonPlan, $request->validated());

        return response()->json([
            'message' => 'Lesson plan updated',
            'data' => new LessonPlanResource($plan),
        ]);
    }

    public function publish(PublishLessonPlanRequest $request, LessonPlan $lessonPlan): JsonResponse
    {
        $plan = $this->lessonPlanService->publishPlan($lessonPlan, $request->validated());

        return response()->json([
            'message' => 'Lesson plan published',
            'data' => new LessonPlanResource($plan),
        ]);
    }

    public function duplicate(LessonPlan $lessonPlan): JsonResponse
    {
        $plan = $this->lessonPlanService->duplicatePlan($lessonPlan);

        return response()->json([
            'message' => 'Lesson plan duplicated',
            'data' => new LessonPlanResource($plan),
        ], 201);
    }

    public function destroy(LessonPlan $lessonPlan): JsonResponse
    {
        $this->lessonPlanService->deletePlan($lessonPlan);

        return response()->json([
            'message' => 'Lesson plan deleted',
        ]);
    }

    public function share(ShareLessonPlanRequest $request, LessonPlan $lessonPlan): JsonResponse
    {
        $plan = $this->lessonPlanService->share(
            $lessonPlan,
            $request->validated('visibility'),
            $request->validated('share_with_classes') ?? null
        );

        return response()->json([
            'message' => 'Lesson plan visibility updated',
            'data' => new LessonPlanResource($plan),
        ]);
    }

    public function attachLesson(Request $request, LessonPlan $lessonPlan): JsonResponse
    {
        $validated = $request->validate([
            'lesson_id' => 'required|integer|exists:lessons,id',
        ]);

        $plan = $this->lessonPlanService->attachLesson($lessonPlan, $validated['lesson_id']);

        return response()->json([
            'message' => 'Lesson attached to plan',
            'data' => new LessonPlanResource($plan),
        ]);
    }
}

