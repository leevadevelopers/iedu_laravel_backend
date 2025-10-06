<?php

namespace App\Services\V1\Schedule;

use App\Models\V1\Schedule\Lesson;
use App\Models\V1\Schedule\LessonAttendance;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class LessonService extends BaseScheduleService
{
    public function __construct()
    {
        // No repository dependency needed
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

        return Lesson::create($data);
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

        $lesson->update($data);
        return $lesson->fresh();
    }

    public function deleteLesson(Lesson $lesson): bool
    {
        $this->validateSchoolOwnership($lesson);

        // Check if lesson is in progress or completed
        if (in_array($lesson->status, ['in_progress', 'completed'])) {
            throw new \Exception('Cannot delete lesson that is in progress or completed');
        }

        return $lesson->delete();
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
        $query = Lesson::where('school_id', $this->getCurrentSchoolId())
            ->byTeacher($teacherId)
            ->with(['subject', 'class', 'teacher']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date'])) {
            $query->byDate($filters['date']);
        }

        if (!empty($filters['date_range'])) {
            $query->byDateRange($filters['date_range'][0], $filters['date_range'][1]);
        }

        return $query->orderBy('lesson_date')->orderBy('start_time')->get();
    }

    public function getClassLessons(int $classId, array $filters = []): Collection
    {
        $query = Lesson::where('school_id', $this->getCurrentSchoolId())
            ->byClass($classId)
            ->with(['subject', 'class', 'teacher']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['date'])) {
            $query->byDate($filters['date']);
        }

        if (!empty($filters['date_range'])) {
            $query->byDateRange($filters['date_range'][0], $filters['date_range'][1]);
        }

        return $query->orderBy('lesson_date')->orderBy('start_time')->get();
    }

    public function getUpcomingLessons(int $limit = 10, array $filters = []): Collection
    {
        $query = Lesson::where('school_id', $this->getCurrentSchoolId())
            ->upcoming()
            ->with(['subject', 'class', 'teacher']);

        if (!empty($filters['teacher_id'])) {
            $query->byTeacher($filters['teacher_id']);
        }

        if (!empty($filters['class_id'])) {
            $query->byClass($filters['class_id']);
        }

        return $query->limit($limit)->get();
    }

    public function getTodayLessons(array $filters = []): Collection
    {
        $query = Lesson::where('school_id', $this->getCurrentSchoolId())
            ->today()
            ->with(['subject', 'class', 'teacher']);

        if (!empty($filters['teacher_id'])) {
            $query->byTeacher($filters['teacher_id']);
        }

        if (!empty($filters['class_id'])) {
            $query->byClass($filters['class_id']);
        }

        return $query->orderBy('start_time')->get();
    }

    public function getLessonStats(): array
    {
        $schoolId = $this->getCurrentSchoolId();

        return [
            'total_lessons' => Lesson::where('school_id', $schoolId)->count(),
            'scheduled_lessons' => Lesson::where('school_id', $schoolId)->scheduled()->count(),
            'completed_lessons' => Lesson::where('school_id', $schoolId)->completed()->count(),
            'cancelled_lessons' => Lesson::where('school_id', $schoolId)->cancelled()->count(),
            'today_lessons' => Lesson::where('school_id', $schoolId)->today()->count(),
            'this_week_lessons' => Lesson::where('school_id', $schoolId)->thisWeek()->count(),
            'online_lessons' => Lesson::where('school_id', $schoolId)->where('is_online', true)->count(),
            'average_attendance_rate' => Lesson::where('school_id', $schoolId)
                ->whereNotNull('attendance_rate')
                ->avg('attendance_rate') ?? 0
        ];
    }

    public function getAttendanceStats(): array
    {
        $schoolId = $this->getCurrentSchoolId();

        return [
            'total_attendance_records' => LessonAttendance::where('school_id', $schoolId)->count(),
            'present_count' => LessonAttendance::where('school_id', $schoolId)->where('status', 'present')->count(),
            'absent_count' => LessonAttendance::where('school_id', $schoolId)->where('status', 'absent')->count(),
            'late_count' => LessonAttendance::where('school_id', $schoolId)->where('status', 'late')->count(),
            'excused_count' => LessonAttendance::where('school_id', $schoolId)->where('status', 'excused')->count(),
            'average_attendance_rate' => Lesson::where('school_id', $schoolId)
                ->whereNotNull('attendance_rate')
                ->avg('attendance_rate') ?? 0
        ];
    }

    public function exportLessonReport(int $lessonId): array
    {
        $lesson = Lesson::findOrFail($lessonId);
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
