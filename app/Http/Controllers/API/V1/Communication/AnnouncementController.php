<?php

namespace App\Http\Controllers\API\V1\Communication;

use App\Http\Controllers\API\V1\BaseController;
use App\Http\Requests\Communication\StoreAnnouncementRequest;
use App\Http\Requests\Communication\UpdateAnnouncementRequest;
use App\Http\Resources\Communication\AnnouncementResource;
use App\Models\Communication\Announcement;
use App\Models\V1\SIS\Student\FamilyRelationship;
use App\Models\V1\SIS\Student\Student;
use App\Services\SMS\SMSService;
use App\Services\SchoolContextService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends BaseController
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
     * List announcements
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();

            $query = Announcement::query();

            if ($schoolId) {
                $query->where('school_id', $schoolId);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            $announcements = $query->with('creator')
                ->latest()
                ->paginate($request->get('per_page', 15));

            return $this->paginatedResponse(
                AnnouncementResource::collection($announcements),
                'Announcements retrieved successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve announcements: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Create announcement
     */
    public function store(StoreAnnouncementRequest $request): JsonResponse
    {
        try {
            $schoolId = $this->getCurrentSchoolId();

            $announcement = Announcement::create([
                'title' => $request->title,
                'content' => $request->content,
                'recipients' => $request->recipients,
                'channels' => $request->channels,
                'school_id' => $schoolId,
                'status' => $request->scheduled_at ? 'scheduled' : 'draft',
                'scheduled_at' => $request->scheduled_at,
                'created_by' => auth('api')->id(),
            ]);

            // If not scheduled, publish immediately if requested
            if (!$request->scheduled_at && $request->get('publish_now', false)) {
                $this->publishAnnouncement($announcement);
            }

            return $this->successResponse(
                new AnnouncementResource($announcement->load('creator')),
                'Announcement created successfully',
                201
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to create announcement: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Show announcement
     */
    public function show(Announcement $announcement): JsonResponse
    {
        return $this->successResponse(
            new AnnouncementResource($announcement->load('creator')),
            'Announcement retrieved successfully'
        );
    }

    /**
     * Update announcement
     */
    public function update(UpdateAnnouncementRequest $request, Announcement $announcement): JsonResponse
    {
        try {
            // Can't update published announcements
            if ($announcement->isPublished()) {
                return $this->errorResponse('Cannot update published announcement', 422);
            }

            $data = $request->validated();

            // Update status if scheduled_at is provided
            if (isset($data['scheduled_at'])) {
                $data['status'] = 'scheduled';
            }

            $announcement->update($data);

            return $this->successResponse(
                new AnnouncementResource($announcement->fresh()->load('creator')),
                'Announcement updated successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to update announcement: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Delete announcement
     */
    public function destroy(Announcement $announcement): JsonResponse
    {
        try {
            // Can't delete published announcements
            if ($announcement->isPublished()) {
                return $this->errorResponse('Cannot delete published announcement', 422);
            }

            $announcement->delete();

            return $this->successResponse(
                null,
                'Announcement deleted successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to delete announcement: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Publish announcement
     */
    public function publish(Announcement $announcement): JsonResponse
    {
        try {
            if ($announcement->isPublished()) {
                return $this->errorResponse('Announcement already published', 422);
            }

            $this->publishAnnouncement($announcement);

            return $this->successResponse(
                new AnnouncementResource($announcement->fresh()->load('creator')),
                'Announcement published successfully'
            );
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to publish announcement: ' . $e->getMessage(),
                500
            );
        }
    }

    /**
     * Publish announcement and send to recipients
     */
    protected function publishAnnouncement(Announcement $announcement): void
    {
        DB::beginTransaction();
        try {
            $announcement->update([
                'status' => 'published',
                'published_at' => now(),
            ]);

            // Get recipients
            $recipients = $this->getRecipients($announcement);

            // Send via SMS if SMS is in channels
            if (in_array('sms', $announcement->channels ?? [])) {
                $this->sendSMSAnnouncement($announcement, $recipients);
            }

            // TODO: Send via portal notifications
            // TODO: Send via WhatsApp if implemented

            // Track in metadata
            $metadata = $announcement->metadata ?? [];
            $metadata['sent_at'] = now()->toISOString();
            $metadata['recipient_count'] = count($recipients);
            $announcement->update(['metadata' => $metadata]);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    /**
     * Get recipients for announcement
     */
    protected function getRecipients(Announcement $announcement): array
    {
        $recipients = [];
        $recipientConfig = $announcement->recipients ?? [];

        // Get all parents
        if (in_array('all_parents', $recipientConfig)) {
            $parentRelationships = FamilyRelationship::where('school_id', $announcement->school_id)
                ->where('status', 'active')
                ->with('guardian')
                ->get();

            foreach ($parentRelationships as $relationship) {
                if ($relationship->guardian && $relationship->guardian->phone) {
                    $recipients[] = [
                        'phone' => $relationship->guardian->phone,
                        'user_id' => $relationship->guardian->id,
                        'type' => 'parent',
                    ];
                }
            }
        }

        // Get all teachers
        if (in_array('all_teachers', $recipientConfig)) {
            // TODO: Implement teacher retrieval
        }

        // Get specific classes
        if (isset($recipientConfig['class_ids']) && is_array($recipientConfig['class_ids'])) {
            $students = Student::whereIn('id', function ($query) use ($recipientConfig) {
                $query->select('student_id')
                    ->from('student_class_enrollments')
                    ->whereIn('class_id', $recipientConfig['class_ids']);
            })->with('familyRelationships.guardian')->get();

            foreach ($students as $student) {
                foreach ($student->familyRelationships as $relationship) {
                    if ($relationship->guardian && $relationship->guardian->phone) {
                        $recipients[] = [
                            'phone' => $relationship->guardian->phone,
                            'user_id' => $relationship->guardian->id,
                            'type' => 'parent',
                            'student_id' => $student->id,
                        ];
                    }
                }
            }
        }

        // Remove duplicates
        $uniqueRecipients = [];
        $seenPhones = [];
        foreach ($recipients as $recipient) {
            if (!in_array($recipient['phone'], $seenPhones)) {
                $uniqueRecipients[] = $recipient;
                $seenPhones[] = $recipient['phone'];
            }
        }

        return $uniqueRecipients;
    }

    /**
     * Send announcement via SMS
     */
    protected function sendSMSAnnouncement(Announcement $announcement, array $recipients): void
    {
        $phones = array_column($recipients, 'phone');
        $message = $announcement->title . "\n\n" . $announcement->content;

        $this->smsService->sendBulk($phones, $message, 'announcement', $announcement->school_id);
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

