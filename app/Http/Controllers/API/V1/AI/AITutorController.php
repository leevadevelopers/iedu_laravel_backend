<?php

namespace App\Http\Controllers\API\V1\AI;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\AI\AskQuestionRequest;
use App\Http\Resources\AI\AIConversationResource;
use App\Models\AI\AIConversation;
use App\Models\V1\SIS\Student\Student;
use App\Services\AI\ClaudeService;
use App\Services\SchoolContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AITutorController extends BaseController
{
    protected ClaudeService $claudeService;
    protected SchoolContextService $schoolContextService;

    public function __construct(ClaudeService $claudeService, SchoolContextService $schoolContextService)
    {
        $this->middleware('auth:api');
        $this->claudeService = $claudeService;
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Ask a question to AI tutor
     */
    public function ask(AskQuestionRequest $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $student = Student::where('user_id', $user->id)->firstOrFail();

            // Check rate limit
            $rateLimit = $this->checkRateLimit($student->id);
            if (!$rateLimit['allowed']) {
                return $this->errorResponse(
                    'Rate limit exceeded. ' . $rateLimit['message'],
                    429
                );
            }

            $schoolId = $this->getCurrentSchoolId();

            // Check if question is cached
            $cacheKey = 'ai_question_' . md5($request->question . $request->subject);
            $cached = Cache::get($cacheKey);

            if ($cached) {
                // Create conversation record from cache
                $conversation = AIConversation::create([
                    'student_id' => $student->id,
                    'school_id' => $schoolId,
                    'subject' => $request->subject,
                    'question' => $request->question,
                    'answer' => $cached['answer'],
                    'explanation' => $cached['explanation'],
                    'examples' => $cached['examples'],
                    'practice_problems' => $cached['practice_problems'],
                    'context' => $request->context,
                    'tokens_used' => 0,
                    'cost' => 0,
                    'status' => 'completed',
                    'metadata' => ['cached' => true],
                ]);

                return $this->successResponse(
                    new AIConversationResource($conversation),
                    'Question answered successfully (from cache)'
                );
            }

            // Process image if provided
            $imageBase64 = null;
            if ($request->image) {
                $imageBase64 = $this->processImage($request->image);
            }

            // Ask Claude
            $response = $this->claudeService->askQuestion(
                $request->question,
                $request->subject,
                $request->context,
                $imageBase64
            );

            // Create conversation record
            $conversation = AIConversation::create([
                'student_id' => $student->id,
                'school_id' => $schoolId,
                'subject' => $request->subject,
                'question' => $request->question,
                'answer' => $response['answer'],
                'explanation' => $response['explanation'],
                'examples' => $response['examples'],
                'practice_problems' => $response['practice_problems'],
                'context' => $request->context,
                'tokens_used' => $response['tokens_used'],
                'cost' => $response['cost'],
                'status' => 'completed',
            ]);

            // Cache the response for common questions
            Cache::put($cacheKey, [
                'answer' => $response['answer'],
                'explanation' => $response['explanation'],
                'examples' => $response['examples'],
                'practice_problems' => $response['practice_problems'],
            ], now()->addDays(7));

            // Increment usage counter
            $this->incrementUsage($student->id);

            return $this->successResponse(
                new AIConversationResource($conversation),
                'Question answered successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to process question: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get conversation history
     */
    public function history(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $student = Student::where('user_id', $user->id)->firstOrFail();

            $query = AIConversation::where('student_id', $student->id)
                ->orderBy('created_at', 'desc');

            if ($request->filled('subject')) {
                $query->where('subject', $request->subject);
            }

            $conversations = $query->limit($request->get('limit', 50))->get();

            return $this->successResponse(
                AIConversationResource::collection($conversations),
                'Conversation history retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve conversation history: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get usage statistics
     */
    public function usage(Request $request): JsonResponse
    {
        try {
            $user = auth('api')->user();
            $student = Student::where('user_id', $user->id)->firstOrFail();

            $today = $this->getTodayUsage($student->id);
            $limit = $this->getUsageLimit($student);
            $plan = $this->getStudentPlan($student);

            return $this->successResponse([
                'today' => $today,
                'limit' => $limit,
                'plan' => $plan,
                'remaining' => max(0, $limit - $today),
            ], 'Usage statistics retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve usage statistics: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Check rate limit
     */
    protected function checkRateLimit(int $studentId): array
    {
        $today = $this->getTodayUsage($studentId);
        $limit = $this->getUsageLimit(Student::find($studentId));

        if ($today >= $limit) {
            return [
                'allowed' => false,
                'message' => "You have reached your daily limit of {$limit} questions. Please try again tomorrow.",
            ];
        }

        return [
            'allowed' => true,
            'message' => '',
        ];
    }

    /**
     * Get today's usage
     */
    protected function getTodayUsage(int $studentId): int
    {
        $cacheKey = "ai_usage_today_{$studentId}";

        return Cache::remember($cacheKey, now()->endOfDay(), function () use ($studentId) {
            return AIConversation::where('student_id', $studentId)
                ->whereDate('created_at', today())
                ->count();
        });
    }

    /**
     * Get usage limit for student
     */
    protected function getUsageLimit(?Student $student): int
    {
        // Default free plan limit
        return config('services.ai.free_plan_limit', 10);
    }

    /**
     * Get student plan
     */
    protected function getStudentPlan(Student $student): string
    {
        // Default to free plan
        return 'free';
    }

    /**
     * Increment usage counter
     */
    protected function incrementUsage(int $studentId): void
    {
        $cacheKey = "ai_usage_today_{$studentId}";
        Cache::increment($cacheKey);
    }

    /**
     * Process base64 image
     */
    protected function processImage(string $imageBase64): string
    {
        // Remove data URL prefix if present
        if (str_starts_with($imageBase64, 'data:image')) {
            $imageBase64 = preg_replace('/^data:image\/\w+;base64,/', '', $imageBase64);
        }

        return $imageBase64;
    }

    /**
     * Get current school ID helper
     */
    protected function getCurrentSchoolId(): ?int
    {
        try {
            return $this->schoolContextService->getCurrentSchoolId();
        } catch (\Exception $e) {
            return null;
        }
    }
}

