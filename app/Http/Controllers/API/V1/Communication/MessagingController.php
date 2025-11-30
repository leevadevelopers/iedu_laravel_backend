<?php

namespace App\Http\Controllers\API\V1\Communication;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Communication\SendMessageRequest;
use App\Http\Resources\Communication\MessageResource;
use App\Models\Communication\Message;
use App\Services\SMS\SMSService;
use App\Services\SchoolContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MessagingController extends BaseController
{
    protected SMSService $smsService;
    protected SchoolContextService $schoolContextService;

    public function __construct(SMSService $smsService, SchoolContextService $schoolContextService)
    {
        $this->middleware('auth:api');
        $this->smsService = $smsService;
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Send a message
     */
    public function send(SendMessageRequest $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();
            $senderId = auth('api')->id();

            DB::beginTransaction();

            $message = Message::create([
                'sender_id' => $senderId,
                'subject' => $request->subject,
                'message' => $request->message,
                'thread_id' => $request->thread_id,
                'class_id' => $request->class_id,
                'recipient_ids' => $request->recipients,
                'student_ids' => $request->students,
                'channels' => $request->channels,
                'school_id' => $schoolId,
            ]);

            // Send via SMS if SMS is in channels
            if (in_array('sms', $request->channels)) {
                $this->sendSMSNotifications($message);
            }

            // TODO: Send portal notifications

            DB::commit();

            return $this->successResponse(
                new MessageResource($message->load('sender')),
                'Message sent successfully',
                201
            );
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse(
                'Failed to send message: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get inbox messages
     */
    public function inbox(Request $request): JsonResponse
    {
        try {
            $userId = auth('api')->id();
            $schoolId = $this->getCurrentSchoolId();

            $query = Message::whereJsonContains('recipient_ids', $userId)
                ->where('school_id', $schoolId)
                ->with(['sender', 'academicClass'])
                ->orderBy('created_at', 'desc');

            if ($request->filled('unread_only')) {
                $query->where('is_read', false);
            }

            $messages = $query->paginate($request->get('per_page', 15));

            return $this->paginatedResponse(
                MessageResource::collection($messages),
                'Inbox messages retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve inbox: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get sent messages
     */
    public function sent(Request $request): JsonResponse
    {
        try {
            $userId = auth('api')->id();
            $schoolId = $this->getCurrentSchoolId();

            $query = Message::where('sender_id', $userId)
                ->where('school_id', $schoolId)
                ->with(['academicClass'])
                ->orderBy('created_at', 'desc');

            $messages = $query->paginate($request->get('per_page', 15));

            return $this->paginatedResponse(
                MessageResource::collection($messages),
                'Sent messages retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve sent messages: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Mark message as read
     */
    public function markAsRead(Message $message): JsonResponse
    {
        try {
            $userId = auth('api')->id();

            // Verify user is a recipient
            if (!in_array($userId, $message->recipient_ids ?? [])) {
                return $this->errorResponse('You are not a recipient of this message', 403);
            }

            $message->markAsRead();

            return $this->successResponse(
                new MessageResource($message->load('sender')),
                'Message marked as read'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to mark message as read: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Get message thread
     */
    public function thread(Message $message): JsonResponse
    {
        try {
            $userId = auth('api')->id();

            // Verify user has access to this message
            $hasAccess = $message->sender_id === $userId ||
                        in_array($userId, $message->recipient_ids ?? []);

            if (!$hasAccess) {
                return $this->errorResponse('Access denied', 403);
            }

            // Get thread (original message and all replies)
            $threadId = $message->thread_id ?? $message->id;
            $thread = Message::where(function ($query) use ($threadId, $message) {
                $query->where('id', $threadId)
                      ->orWhere('thread_id', $threadId);
            })
            ->with(['sender', 'replies.sender'])
            ->orderBy('created_at')
            ->get();

            return $this->successResponse(
                MessageResource::collection($thread),
                'Message thread retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve message thread: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Send SMS notifications for message
     */
    protected function sendSMSNotifications(Message $message): void
    {
        $recipients = \App\Models\User::whereIn('id', $message->recipient_ids)
            ->whereNotNull('phone')
            ->get();

        $phones = $recipients->pluck('phone')->toArray();
        $smsMessage = $message->subject . "\n\n" . $message->message;

        $this->smsService->sendBulk($phones, $smsMessage, 'message', $message->school_id);
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

