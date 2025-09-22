<?php

namespace App\Services\V1\Schedule;

use App\Models\V1\Schedule\Lesson;
use App\Models\V1\Schedule\LessonAttendance;
use App\Repositories\V1\Schedule\LessonRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LessonService extends BaseScheduleService
{
    protected LessonRepository $lessonRepository;

    public function __construct(LessonRepository $lessonRepository)
    {
        $this->lessonRepository = $lessonRepository;
    }

    public function getLessonRepository(): LessonRepository
    {
        return $this->lessonRepository;
    }

    public function createLesson(array $data)
    {
        $data['school_id'] = $this->getCurrentSchoolId();
        $data['created_by'] = Auth::id();

        // Calculate duration
        if (isset($data['start_time']) && isset($data['end_time'])) {
            $start = \Carbon\Carbon::parse($data['start_time']);
            $end = \Carbon\Carbon::parse($data['end_time']);
            $data['duration_minutes'] = $end->diffInMinutes($start);
        }

        return $this->lessonRepository->create($data);
    }

    public function updateLesson(Lesson $lesson, array $data)
    {
        $this->validateSchoolOwnership($lesson);

        $data['updated_by'] = Auth::id();

        // Recalculate duration if times changed
        if (isset($data['start_time']) && isset($data['end_time'])) {
            $start = \Carbon\Carbon::parse($data['start_time']);
            $end = \Carbon\Carbon::parse($data['end_time']);
            $data['duration_minutes'] = $end->diffInMinutes($start);
        }

        return $this->lessonRepository->update($lesson, $data);
    }

    public function deleteLesson(Lesson $lesson): bool
    {
        $this->validateSchoolOwnership($lesson);

        // Check if lesson is in progress or completed
        if (in_array($lesson->status, ['in_progress', 'completed'])) {
            throw new \Exception('Cannot delete lesson that is in progress or completed');
        }

        return $this->lessonRepository->delete($lesson);
    }

    public function startLesson(Lesson $lesson): bool
    {
        $this->validateSchoolOwnership($lesson);

        if ($lesson->status !== 'scheduled') {
            throw new \Exception('Only scheduled lessons can be started');
        }

        return $lesson->update(['status' => 'in_progress']);
    }

    public function completeLesson(Lesson $lesson, array $data = []): bool
    {
        $this->validateSchoolOwnership($lesson);

        if (!in_array($lesson->status, ['scheduled', 'in_progress'])) {
            throw new \Exception('Only scheduled or in-progress lessons can be completed');
        }

        return $lesson->markAsCompleted($data);
    }

    public function cancelLesson(Lesson $lesson, string $reason = null): bool
    {
        $this->validateSchoolOwnership($lesson);

        if ($lesson->status === 'completed') {
            throw new \Exception('Cannot cancel a completed lesson');
        }

        return $lesson->cancel($reason);
    }

    public function addLessonContent(Lesson $lesson, array $contentData): \App\Models\V1\Schedule\LessonContent
    {
        $this->validateSchoolOwnership($lesson);

        $contentData['school_id'] = $this->getCurrentSchoolId();
        $contentData['lesson_id'] = $lesson->id;
        $contentData['uploaded_by'] = Auth::id();

        // Handle file upload
        if (isset($contentData['file']) && $contentData['file'] instanceof \Illuminate\Http\UploadedFile) {
            $file = $contentData['file'];
            $path = $file->store('lesson-contents', 'public');

            $contentData['file_name'] = $file->getClientOriginalName();
            $contentData['file_path'] = $path;
            $contentData['file_type'] = $file->getClientOriginalExtension();
            $contentData['file_size'] = $file->getSize();
            $contentData['mime_type'] = $file->getMimeType();

            unset($contentData['file']);
        }

        return $lesson->contents()->create($contentData);
    }

    public function markAttendance(Lesson $lesson, array $attendanceData): Collection
    {
        $this->validateSchoolOwnership($lesson);

        $attendances = collect();

        DB::beginTransaction();
        try {
            foreach ($attendanceData as $studentData) {
                $attendance = LessonAttendance::updateOrCreate(
                    [
                        'lesson_id' => $lesson->id,
                        'student_id' => $studentData['student_id']
                    ],
                    array_merge($studentData, [
                        'school_id' => $this->getCurrentSchoolId(),
                        'marked_by' => Auth::id()
                    ])
                );

                $attendances->push($attendance);
            }

            // Update lesson attendance stats
            $lesson->updateAttendanceStats();

            DB::commit();
            return $attendances;
        } catch (\Exception $e) {
            DB::rollback();
            throw $e;
        }
    }

    public function generateQRCode(Lesson $lesson): array
    {
        $this->validateSchoolOwnership($lesson);

        // Generate unique token for QR code
        $token = Str::random(32);

        // Store token temporarily (you might want to use cache or database)
        Cache::put("lesson_qr_{$lesson->id}", $token, now()->addMinutes(30));

        // Generate QR code URL
        $qrUrl = route('api.v1.lessons.check-in-qr', ['lesson' => $lesson->id, 'token' => $token]);

        return [
            'qr_url' => $qrUrl,
            'token' => $token,
            'expires_at' => now()->addMinutes(30)->toISOString()
        ];
    }

    public function checkInWithQR(Lesson $lesson, string $token, int $studentId): LessonAttendance
    {
        $this->validateSchoolOwnership($lesson);

        // Validate QR token
        $cachedToken = Cache::get("lesson_qr_{$lesson->id}");
        if ($cachedToken !== $token) {
            throw new \Exception('Invalid or expired QR code');
        }

        // Mark attendance
        $attendance = LessonAttendance::updateOrCreate(
            [
                'lesson_id' => $lesson->id,
                'student_id' => $studentId
            ],
            [
                'school_id' => $this->getCurrentSchoolId(),
                'status' => 'present',
                'marked_by_method' => 'qr_code',
                'arrival_time' => now(),
                'marked_by' => $studentId // Student marked themselves
            ]
        );

        // Update lesson stats
        $lesson->updateAttendanceStats();

        return $attendance;
    }

    public function getTeacherLessons(int $teacherId, array $filters = []): Collection
    {
        return $this->lessonRepository->getByTeacher($teacherId, $filters);
    }

    public function getClassLessons(int $classId, array $filters = []): Collection
    {
        return $this->lessonRepository->getByClass($classId, $filters);
    }

    public function getUpcomingLessons(int $limit = 10, array $filters = []): Collection
    {
        return $this->lessonRepository->getUpcoming($limit, $filters);
    }

    public function getTodayLessons(array $filters = []): Collection
    {
        return $this->lessonRepository->getToday($filters);
    }

    public function getLessonStats(): array
    {
        return $this->lessonRepository->getDashboardStats();
    }

    public function getAttendanceStats(): array
    {
        return $this->lessonRepository->getAttendanceStats();
    }

    public function exportLessonReport(int $lessonId): array
    {
        $lesson = $this->lessonRepository->find($lessonId);
        $this->validateSchoolOwnership($lesson);

        $lesson->load([
            'subject',
            'class',
            'teacher',
            'attendances.student',
            'contents'
        ]);

        return [
            'lesson' => $lesson,
            'summary' => [
                'total_students' => $lesson->expected_students,
                'present_students' => $lesson->present_students,
                'attendance_rate' => $lesson->attendance_rate,
                'contents_count' => $lesson->contents->count(),
                'duration_minutes' => $lesson->duration_minutes
            ]
        ];
    }
}
