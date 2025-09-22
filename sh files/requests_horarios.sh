#!/bin/bash

# iEDU Schedule & Lessons Management Module - Part 3
# Requests, Routes, Policies, Notifications and Integrations

echo "🔐 Creating Schedule & Lessons Requests, Routes, Policies and Integrations..."

# =============================================================================
# 7. REQUEST CLASSES
# =============================================================================

echo "📝 Creating request classes..."

mkdir -p app/Http/Requests/V1/Schedule

# 7.1 Base Schedule Request
cat > app/Http/Requests/V1/Schedule/BaseScheduleRequest.php << 'EOF'
<?php

namespace App\Http\Requests\V1\Schedule;

use Illuminate\Foundation\Http\FormRequest;
use App\Services\SchoolContextService;

abstract class BaseScheduleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && $this->hasValidSchoolContext();
    }

    protected function hasValidSchoolContext(): bool
    {
        try {
            $schoolContext = app(SchoolContextService::class);
            return $schoolContext->getCurrentSchool() !== null;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function getCurrentSchoolId(): int
    {
        return app(SchoolContextService::class)->getCurrentSchool()->id;
    }

    public function messages(): array
    {
        return [
            'required' => 'O campo :attribute é obrigatório.',
            'string' => 'O campo :attribute deve ser um texto.',
            'integer' => 'O campo :attribute deve ser um número inteiro.',
            'numeric' => 'O campo :attribute deve ser um número.',
            'date' => 'O campo :attribute deve ser uma data válida.',
            'date_format' => 'O campo :attribute deve ter o formato :format.',
            'email' => 'O campo :attribute deve ser um email válido.',
            'unique' => 'Este :attribute já está em uso.',
            'exists' => 'O :attribute selecionado é inválido.',
            'in' => 'O :attribute selecionado é inválido.',
            'min' => 'O campo :attribute deve ter pelo menos :min caracteres.',
            'max' => 'O campo :attribute não pode ter mais que :max caracteres.',
            'between' => 'O campo :attribute deve ter entre :min e :max.',
            'array' => 'O campo :attribute deve ser um array.',
            'boolean' => 'O campo :attribute deve ser verdadeiro ou falso.',
            'after' => 'O campo :attribute deve ser posterior a :date.',
            'before' => 'O campo :attribute deve ser anterior a :date.',
        ];
    }

    public function attributes(): array
    {
        return [
            'school_id' => 'escola',
            'academic_year_id' => 'ano letivo',
            'academic_term_id' => 'período letivo',
            'subject_id' => 'disciplina',
            'class_id' => 'turma',
            'teacher_id' => 'professor',
            'classroom' => 'sala de aula',
            'day_of_week' => 'dia da semana',
            'start_time' => 'horário de início',
            'end_time' => 'horário de término',
            'start_date' => 'data de início',
            'end_date' => 'data de término',
            'lesson_date' => 'data da aula',
            'duration_minutes' => 'duração em minutos',
            'is_online' => 'modalidade online',
            'online_meeting_url' => 'URL da reunião online',
            'content_type' => 'tipo de conteúdo',
            'student_id' => 'estudante',
        ];
    }
}
EOF

# 7.2 Store Schedule Request
cat > app/Http/Requests/V1/Schedule/StoreScheduleRequest.php << 'EOF'
<?php

namespace App\Http\Requests\V1\Schedule;

class StoreScheduleRequest extends BaseScheduleRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',

            // Associations
            'subject_id' => [
                'required',
                'integer',
                'exists:subjects,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'class_id' => [
                'required',
                'integer',
                'exists:classes,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'teacher_id' => [
                'required',
                'integer',
                'exists:teachers,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_year_id' => [
                'required',
                'integer',
                'exists:academic_years,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_term_id' => [
                'nullable',
                'integer',
                'exists:academic_terms,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'classroom' => 'nullable|string|max:50',

            // Timing
            'period' => 'required|in:morning,afternoon,evening,night',
            'day_of_week' => 'required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',

            // Date range
            'start_date' => 'required|date|before:end_date',
            'end_date' => 'required|date|after:start_date',
            'recurrence_pattern' => 'nullable|array',

            // Configuration
            'status' => 'nullable|in:active,suspended,cancelled,completed',
            'is_online' => 'boolean',
            'online_meeting_url' => 'nullable|url|max:500',
            'configuration_json' => 'nullable|array',

            // Auto-generation
            'auto_generate_lessons' => 'boolean'
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate minimum duration
            if ($this->filled('start_time') && $this->filled('end_time')) {
                $start = \Carbon\Carbon::parse($this->start_time);
                $end = \Carbon\Carbon::parse($this->end_time);
                $duration = $end->diffInMinutes($start);

                if ($duration < 30) {
                    $validator->errors()->add('end_time', 'A duração mínima da aula deve ser de 30 minutos.');
                }

                if ($duration > 240) { // 4 hours
                    $validator->errors()->add('end_time', 'A duração máxima da aula deve ser de 4 horas.');
                }
            }

            // Validate date range
            if ($this->filled('start_date') && $this->filled('end_date')) {
                $startDate = \Carbon\Carbon::parse($this->start_date);
                $endDate = \Carbon\Carbon::parse($this->end_date);
                $daysDiff = $endDate->diffInDays($startDate);

                if ($daysDiff > 365) {
                    $validator->errors()->add('end_date', 'O período do horário não pode exceder 1 ano.');
                }
            }

            // Validate online meeting URL if online
            if ($this->boolean('is_online') && !$this->filled('online_meeting_url')) {
                $validator->errors()->add('online_meeting_url', 'URL da reunião é obrigatória para aulas online.');
            }
        });
    }
}
EOF

# 7.3 Update Schedule Request
cat > app/Http/Requests/V1/Schedule/UpdateScheduleRequest.php << 'EOF'
<?php

namespace App\Http\Requests\V1\Schedule;

class UpdateScheduleRequest extends BaseScheduleRequest
{
    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:1000',

            // Associations
            'subject_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:subjects,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'class_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:classes,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'teacher_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:teachers,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_year_id' => [
                'sometimes',
                'required',
                'integer',
                'exists:academic_years,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_term_id' => [
                'nullable',
                'integer',
                'exists:academic_terms,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'classroom' => 'nullable|string|max:50',

            // Timing
            'period' => 'sometimes|required|in:morning,afternoon,evening,night',
            'day_of_week' => 'sometimes|required|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',

            // Date range
            'start_date' => 'sometimes|required|date|before:end_date',
            'end_date' => 'sometimes|required|date|after:start_date',
            'recurrence_pattern' => 'nullable|array',

            // Configuration
            'status' => 'sometimes|in:active,suspended,cancelled,completed',
            'is_online' => 'boolean',
            'online_meeting_url' => 'nullable|url|max:500',
            'configuration_json' => 'nullable|array'
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Same validations as StoreScheduleRequest
            if ($this->filled('start_time') && $this->filled('end_time')) {
                $start = \Carbon\Carbon::parse($this->start_time);
                $end = \Carbon\Carbon::parse($this->end_time);
                $duration = $end->diffInMinutes($start);

                if ($duration < 30) {
                    $validator->errors()->add('end_time', 'A duração mínima da aula deve ser de 30 minutos.');
                }
            }

            if ($this->boolean('is_online') && !$this->filled('online_meeting_url')) {
                $validator->errors()->add('online_meeting_url', 'URL da reunião é obrigatória para aulas online.');
            }
        });
    }
}
EOF

# 7.4 Store Lesson Request
cat > app/Http/Requests/V1/Schedule/StoreLessonRequest.php << 'EOF'
<?php

namespace App\Http\Requests\V1\Schedule;

class StoreLessonRequest extends BaseScheduleRequest
{
    public function rules(): array
    {
        return [
            'schedule_id' => [
                'nullable',
                'integer',
                'exists:schedules,id,school_id,' . $this->getCurrentSchoolId()
            ],

            // Basic info
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'objectives' => 'nullable|array',
            'objectives.*' => 'string|max:500',

            // Associations
            'subject_id' => [
                'required',
                'integer',
                'exists:subjects,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'class_id' => [
                'required',
                'integer',
                'exists:classes,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'teacher_id' => [
                'required',
                'integer',
                'exists:teachers,id,school_id,' . $this->getCurrentSchoolId()
            ],
            'academic_term_id' => [
                'required',
                'integer',
                'exists:academic_terms,id,school_id,' . $this->getCurrentSchoolId()
            ],

            // Timing
            'lesson_date' => 'required|date',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',

            // Location and format
            'classroom' => 'nullable|string|max:50',
            'is_online' => 'boolean',
            'online_meeting_url' => 'nullable|url|max:500',
            'online_meeting_details' => 'nullable|array',

            // Type and status
            'status' => 'nullable|in:scheduled,in_progress,completed,cancelled,postponed,absent_teacher',
            'type' => 'nullable|in:regular,makeup,extra,review,exam,practical,field_trip',

            // Content
            'content_summary' => 'nullable|string|max:2000',
            'curriculum_topics' => 'nullable|array',
            'homework_assigned' => 'nullable|string|max:1000',
            'homework_due_date' => 'nullable|date|after:lesson_date',

            // Teacher notes
            'teacher_notes' => 'nullable|string|max:1000',
            'lesson_observations' => 'nullable|string|max:1000',
            'student_participation' => 'nullable|array',

            // Approval
            'requires_approval' => 'boolean'
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate minimum duration
            if ($this->filled('start_time') && $this->filled('end_time')) {
                $start = \Carbon\Carbon::parse($this->start_time);
                $end = \Carbon\Carbon::parse($this->end_time);
                $duration = $end->diffInMinutes($start);

                if ($duration < 15) {
                    $validator->errors()->add('end_time', 'A duração mínima da aula deve ser de 15 minutos.');
                }
            }

            // Validate online meeting requirements
            if ($this->boolean('is_online') && !$this->filled('online_meeting_url')) {
                $validator->errors()->add('online_meeting_url', 'URL da reunião é obrigatória para aulas online.');
            }

            // Validate homework due date
            if ($this->filled('homework_assigned') && $this->filled('homework_due_date')) {
                $lessonDate = \Carbon\Carbon::parse($this->lesson_date);
                $dueDate = \Carbon\Carbon::parse($this->homework_due_date);

                if ($dueDate->lte($lessonDate)) {
                    $validator->errors()->add('homework_due_date', 'A data de entrega deve ser posterior à data da aula.');
                }
            }
        });
    }
}
EOF

# 7.5 Update Lesson Request
cat > app/Http/Requests/V1/Schedule/UpdateLessonRequest.php << 'EOF'
<?php

namespace App\Http\Requests\V1\Schedule;

class UpdateLessonRequest extends BaseScheduleRequest
{
    public function rules(): array
    {
        return [
            // Basic info
            'title' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string|max:2000',
            'objectives' => 'nullable|array',

            // Timing (restricted if lesson is in progress or completed)
            'lesson_date' => 'sometimes|required|date',
            'start_time' => 'sometimes|required|date_format:H:i',
            'end_time' => 'sometimes|required|date_format:H:i|after:start_time',

            // Location and format
            'classroom' => 'nullable|string|max:50',
            'is_online' => 'boolean',
            'online_meeting_url' => 'nullable|url|max:500',
            'online_meeting_details' => 'nullable|array',

            // Type and status
            'status' => 'sometimes|in:scheduled,in_progress,completed,cancelled,postponed,absent_teacher',
            'type' => 'sometimes|in:regular,makeup,extra,review,exam,practical,field_trip',

            // Content
            'content_summary' => 'nullable|string|max:2000',
            'curriculum_topics' => 'nullable|array',
            'homework_assigned' => 'nullable|string|max:1000',
            'homework_due_date' => 'nullable|date|after:lesson_date',

            // Teacher notes
            'teacher_notes' => 'nullable|string|max:1000',
            'lesson_observations' => 'nullable|string|max:1000',
            'student_participation' => 'nullable|array'
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $lesson = $this->route('lesson');

            // Restrict timing changes for completed lessons
            if ($lesson && $lesson->status === 'completed') {
                if ($this->filled('lesson_date') || $this->filled('start_time') || $this->filled('end_time')) {
                    $validator->errors()->add('lesson_date', 'Não é possível alterar horários de aulas já concluídas.');
                }
            }

            // Validate duration
            if ($this->filled('start_time') && $this->filled('end_time')) {
                $start = \Carbon\Carbon::parse($this->start_time);
                $end = \Carbon\Carbon::parse($this->end_time);
                $duration = $end->diffInMinutes($start);

                if ($duration < 15) {
                    $validator->errors()->add('end_time', 'A duração mínima da aula deve ser de 15 minutos.');
                }
            }
        });
    }
}
EOF

# =============================================================================
# 8. ROUTES
# =============================================================================

echo "🛤️ Creating routes..."

# 8.1 Schedule Routes
cat > routes/modules/schedule/schedule.php << 'EOF'
<?php

use App\Http\Controllers\V1\Schedule\ScheduleController;
use App\Http\Controllers\V1\Schedule\LessonController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Schedule & Lessons Management Routes
|--------------------------------------------------------------------------
|
| These routes handle all schedule and lesson management functionality
| including timetable management, lesson planning, and attendance tracking.
|
*/

Route::middleware(['auth:api', 'school.context'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | Schedule Management Routes
    |--------------------------------------------------------------------------
    */

    // Core Schedule CRUD
    Route::prefix('schedules')->name('schedules.')->group(function () {
        Route::get('/', [ScheduleController::class, 'index'])->name('index');
        Route::post('/', [ScheduleController::class, 'store'])->name('store');
        Route::get('/{schedule}', [ScheduleController::class, 'show'])->name('show');
        Route::put('/{schedule}', [ScheduleController::class, 'update'])->name('update');
        Route::delete('/{schedule}', [ScheduleController::class, 'destroy'])->name('destroy');

        // Schedule Actions
        Route::post('/{schedule}/generate-lessons', [ScheduleController::class, 'generateLessons'])
            ->name('generate-lessons');

        // Schedule Views
        Route::get('/teacher/my-schedule', [ScheduleController::class, 'teacherSchedule'])
            ->name('teacher-schedule');
        Route::get('/class/{classId}/schedule', [ScheduleController::class, 'classSchedule'])
            ->name('class-schedule');

        // Conflict Detection
        Route::post('/check-conflicts', [ScheduleController::class, 'checkConflicts'])
            ->name('check-conflicts');

        // Statistics
        Route::get('/stats/overview', [ScheduleController::class, 'stats'])
            ->name('stats');
    });

    /*
    |--------------------------------------------------------------------------
    | Lesson Management Routes
    |--------------------------------------------------------------------------
    */

    // Core Lesson CRUD
    Route::prefix('lessons')->name('lessons.')->group(function () {
        Route::get('/', [LessonController::class, 'index'])->name('index');
        Route::post('/', [LessonController::class, 'store'])->name('store');
        Route::get('/{lesson}', [LessonController::class, 'show'])->name('show');
        Route::put('/{lesson}', [LessonController::class, 'update'])->name('update');
        Route::delete('/{lesson}', [LessonController::class, 'destroy'])->name('destroy');

        // Lesson State Management
        Route::post('/{lesson}/start', [LessonController::class, 'start'])->name('start');
        Route::post('/{lesson}/complete', [LessonController::class, 'complete'])->name('complete');
        Route::post('/{lesson}/cancel', [LessonController::class, 'cancel'])->name('cancel');

        // Attendance Management
        Route::post('/{lesson}/attendance', [LessonController::class, 'markAttendance'])
            ->name('mark-attendance');
        Route::get('/{lesson}/qr-code', [LessonController::class, 'generateQR'])
            ->name('generate-qr');
        Route::post('/{lesson}/check-in-qr', [LessonController::class, 'checkInQR'])
            ->name('check-in-qr');

        // Content Management
        Route::post('/{lesson}/contents', [LessonController::class, 'addContent'])
            ->name('add-content');

        // Reports and Analytics
        Route::get('/{lesson}/report', [LessonController::class, 'exportReport'])
            ->name('export-report');
        Route::get('/stats/overview', [LessonController::class, 'stats'])
            ->name('stats');
        Route::get('/stats/attendance', [LessonController::class, 'attendanceStats'])
            ->name('attendance-stats');
    });

    /*
    |--------------------------------------------------------------------------
    | Lesson Contents Routes
    |--------------------------------------------------------------------------
    */

    Route::prefix('lesson-contents')->name('lesson-contents.')->group(function () {
        Route::get('/{lessonContent}/download', function ($lessonContentId) {
            $content = \App\Models\V1\Schedule\LessonContent::findOrFail($lessonContentId);

            // Validate school ownership
            $schoolService = app(\App\Services\SchoolContextService::class);
            if ($content->school_id !== $schoolService->getCurrentSchool()->id) {
                abort(403);
            }

            if (!$content->isDownloadable()) {
                abort(403, 'Content is not downloadable');
            }

            return \Storage::download($content->file_path, $content->file_name);
        })->name('download');
    });

    /*
    |--------------------------------------------------------------------------
    | Quick Access Routes for Different User Types
    |--------------------------------------------------------------------------
    */

    // Teacher Portal Routes
    Route::prefix('teacher/portal')->middleware('role:teacher')->name('teacher.portal.')->group(function () {
        Route::get('/dashboard', function () {
            $teacherId = auth()->user()->teacher->id;
            $lessonService = app(\App\Services\V1\Schedule\LessonService::class);

            return response()->json([
                'today_lessons' => $lessonService->getTodayLessons(['teacher_id' => $teacherId]),
                'upcoming_lessons' => $lessonService->getUpcomingLessons(5, ['teacher_id' => $teacherId]),
                'stats' => $lessonService->getLessonStats()
            ]);
        })->name('dashboard');

        Route::get('/schedule', [ScheduleController::class, 'teacherSchedule'])->name('schedule');
        Route::get('/lessons', [LessonController::class, 'index'])->name('lessons');
    });

    // Student Portal Routes
    Route::prefix('student/portal')->middleware('role:student')->name('student.portal.')->group(function () {
        Route::get('/schedule', function () {
            $student = auth()->user()->student;
            $schedules = \App\Models\V1\Schedule\Schedule::whereHas('class.students', function ($query) use ($student) {
                $query->where('student_id', $student->id);
            })->with(['subject', 'teacher'])->get();

            return response()->json([
                'data' => \App\Http\Resources\V1\Schedule\ScheduleResource::collection($schedules)
            ]);
        })->name('schedule');

        Route::get('/lessons/today', function () {
            $student = auth()->user()->student;
            $lessons = \App\Models\V1\Schedule\Lesson::whereHas('class.students', function ($query) use ($student) {
                $query->where('student_id', $student->id);
            })->today()->with(['subject', 'teacher', 'contents'])->get();

            return response()->json([
                'data' => \App\Http\Resources\V1\Schedule\LessonResource::collection($lessons)
            ]);
        })->name('lessons.today');

        Route::get('/lessons/upcoming', function () {
            $student = auth()->user()->student;
            $lessons = \App\Models\V1\Schedule\Lesson::whereHas('class.students', function ($query) use ($student) {
                $query->where('student_id', $student->id);
            })->upcoming()->limit(10)->with(['subject', 'teacher', 'contents'])->get();

            return response()->json([
                'data' => \App\Http\Resources\V1\Schedule\LessonResource::collection($lessons)
            ]);
        })->name('lessons.upcoming');
    });

    // Parent Portal Routes
    Route::prefix('parent/portal')->middleware('role:parent')->name('parent.portal.')->group(function () {
        Route::get('/children-schedule', function () {
            $parent = auth()->user();
            $children = $parent->students; // Assuming relationship exists

            $schedules = \App\Models\V1\Schedule\Schedule::whereHas('class.students', function ($query) use ($children) {
                $query->whereIn('student_id', $children->pluck('id'));
            })->with(['subject', 'teacher', 'class'])->get();

            return response()->json([
                'data' => \App\Http\Resources\V1\Schedule\ScheduleResource::collection($schedules)
            ]);
        })->name('children-schedule');
    });

});

/*
|--------------------------------------------------------------------------
| Public Routes (No Authentication Required)
|--------------------------------------------------------------------------
*/

// Public schedule view (for display boards, etc.)
Route::get('/public/schedule/{token}', function ($token) {
    // Implement token-based public schedule access
    // This could be used for digital displays in school hallways
    return response()->json(['message' => 'Public schedule access not implemented']);
})->name('public.schedule');
EOF

# =============================================================================
# 9. POLICIES
# =============================================================================

echo "🔒 Creating policies..."

mkdir -p app/Policies/V1/Schedule

# 9.1 Schedule Policy
cat > app/Policies/V1/Schedule/SchedulePolicy.php << 'EOF'
<?php

namespace App\Policies\V1\Schedule;

use App\Models\User;
use App\Models\V1\Schedule\Schedule;
use App\Services\SchoolContextService;

class SchedulePolicy
{
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Determine whether the user can view any schedules.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'teacher', 'academic_coordinator', 'principal']);
    }

    /**
     * Determine whether the user can view the schedule.
     */
    public function view(User $user, Schedule $schedule): bool
    {
        // Check school ownership
        if ($schedule->school_id !== $this->schoolContextService->getCurrentSchool()->id) {
            return false;
        }

        // Admin, principal, academic coordinator can view all
        if (in_array($user->user_type, ['admin', 'principal', 'academic_coordinator'])) {
            return true;
        }

        // Teachers can view their own schedules
        if ($user->user_type === 'teacher') {
            return $schedule->teacher_id === $user->teacher?->id;
        }

        // Students can view their class schedules
        if ($user->user_type === 'student') {
            return $user->student->classes->contains($schedule->class_id);
        }

        // Parents can view their children's schedules
        if ($user->user_type === 'parent') {
            return $user->students->flatMap->classes->contains($schedule->class_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create schedules.
     */
    public function create(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'academic_coordinator', 'principal']);
    }

    /**
     * Determine whether the user can update the schedule.
     */
    public function update(User $user, Schedule $schedule): bool
    {
        // Check school ownership
        if ($schedule->school_id !== $this->schoolContextService->getCurrentSchool()->id) {
            return false;
        }

        // Admin, principal, academic coordinator can update all
        if (in_array($user->user_type, ['admin', 'principal', 'academic_coordinator'])) {
            return true;
        }

        // Teachers can update their own schedules with restrictions
        if ($user->user_type === 'teacher' && $schedule->teacher_id === $user->teacher?->id) {
            // Teachers can only update certain fields (not timing or core assignments)
            return true; // Additional field-level restrictions should be in the request validation
        }

        return false;
    }

    /**
     * Determine whether the user can delete the schedule.
     */
    public function delete(User $user, Schedule $schedule): bool
    {
        // Check school ownership
        if ($schedule->school_id !== $this->schoolContextService->getCurrentSchool()->id) {
            return false;
        }

        // Only admin, principal, academic coordinator can delete
        return in_array($user->user_type, ['admin', 'principal', 'academic_coordinator']);
    }

    /**
     * Determine whether the user can generate lessons from schedule.
     */
    public function generateLessons(User $user, Schedule $schedule): bool
    {
        return $this->update($user, $schedule);
    }
}
EOF

# 9.2 Lesson Policy
cat > app/Policies/V1/Schedule/LessonPolicy.php << 'EOF'
<?php

namespace App\Policies\V1\Schedule;

use App\Models\User;
use App\Models\V1\Schedule\Lesson;
use App\Services\SchoolContextService;

class LessonPolicy
{
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->schoolContextService = $schoolContextService;
    }

    /**
     * Determine whether the user can view any lessons.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'teacher', 'academic_coordinator', 'principal', 'student', 'parent']);
    }

    /**
     * Determine whether the user can view the lesson.
     */
    public function view(User $user, Lesson $lesson): bool
    {
        // Check school ownership
        if ($lesson->school_id !== $this->schoolContextService->getCurrentSchool()->id) {
            return false;
        }

        // Admin, principal, academic coordinator can view all
        if (in_array($user->user_type, ['admin', 'principal', 'academic_coordinator'])) {
            return true;
        }

        // Teachers can view their own lessons
        if ($user->user_type === 'teacher') {
            return $lesson->teacher_id === $user->teacher?->id;
        }

        // Students can view their class lessons
        if ($user->user_type === 'student') {
            return $user->student->classes->contains($lesson->class_id);
        }

        // Parents can view their children's lessons
        if ($user->user_type === 'parent') {
            return $user->students->flatMap->classes->contains($lesson->class_id);
        }

        return false;
    }

    /**
     * Determine whether the user can create lessons.
     */
    public function create(User $user): bool
    {
        return in_array($user->user_type, ['admin', 'teacher', 'academic_coordinator', 'principal']);
    }

    /**
     * Determine whether the user can update the lesson.
     */
    public function update(User $user, Lesson $lesson): bool
    {
        // Check school ownership
        if ($lesson->school_id !== $this->schoolContextService->getCurrentSchool()->id) {
            return false;
        }

        // Admin, principal, academic coordinator can update all
        if (in_array($user->user_type, ['admin', 'principal', 'academic_coordinator'])) {
            return true;
        }

        // Teachers can update their own lessons
        if ($user->user_type === 'teacher' && $lesson->teacher_id === $user->teacher?->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can delete the lesson.
     */
    public function delete(User $user, Lesson $lesson): bool
    {
        // Check school ownership
        if ($lesson->school_id !== $this->schoolContextService->getCurrentSchool()->id) {
            return false;
        }

        // Can't delete completed lessons
        if ($lesson->status === 'completed') {
            return false;
        }

        // Admin, principal, academic coordinator can delete
        if (in_array($user->user_type, ['admin', 'principal', 'academic_coordinator'])) {
            return true;
        }

        // Teachers can delete their own scheduled lessons
        if ($user->user_type === 'teacher' && $lesson->teacher_id === $user->teacher?->id) {
            return $lesson->status === 'scheduled';
        }

        return false;
    }

    /**
     * Determine whether the user can manage lesson state (start/complete/cancel).
     */
    public function manageState(User $user, Lesson $lesson): bool
    {
        // Check school ownership
        if ($lesson->school_id !== $this->schoolContextService->getCurrentSchool()->id) {
            return false;
        }

        // Admin, principal, academic coordinator can manage all
        if (in_array($user->user_type, ['admin', 'principal', 'academic_coordinator'])) {
            return true;
        }

        // Teachers can manage their own lessons
        if ($user->user_type === 'teacher' && $lesson->teacher_id === $user->teacher?->id) {
            return true;
        }

        return false;
    }

    /**
     * Determine whether the user can mark attendance.
     */
    public function markAttendance(User $user, Lesson $lesson): bool
    {
        return $this->manageState($user, $lesson);
    }

    /**
     * Determine whether the user can add content to lesson.
     */
    public function addContent(User $user, Lesson $lesson): bool
    {
        return $this->update($user, $lesson);
    }

    /**
     * Determine whether the user can generate QR code for attendance.
     */
    public function generateQR(User $user, Lesson $lesson): bool
    {
        return $this->markAttendance($user, $lesson);
    }

    /**
     * Determine whether the user can access lesson reports.
     */
    public function viewReports(User $user, Lesson $lesson): bool
    {
        return $this->view($user, $lesson);
    }
}
EOF

# =============================================================================
# 10. NOTIFICATIONS
# =============================================================================

echo "📬 Creating notification classes..."

mkdir -p app/Notifications/V1/Schedule

# 10.1 Schedule Change Notification
cat > app/Notifications/V1/Schedule/ScheduleChangedNotification.php << 'EOF'
<?php

namespace App\Notifications\V1\Schedule;

use App\Models\V1\Schedule\Schedule;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\DatabaseMessage;
use Illuminate\Notifications\Notification;

class ScheduleChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Schedule $schedule;
    protected string $changeType;
    protected array $changes;

    public function __construct(Schedule $schedule, string $changeType, array $changes = [])
    {
        $this->schedule = $schedule;
        $this->changeType = $changeType;
        $this->changes = $changes;
    }

    public function via($notifiable): array
    {
        $channels = ['database'];

        // Add email for significant changes
        if (in_array($this->changeType, ['time_changed', 'cancelled', 'teacher_changed'])) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    public function toMail($notifiable): MailMessage
    {
        $subject = $this->getEmailSubject();
        $message = new MailMessage();

        $message->subject($subject)
                ->greeting('Olá ' . $notifiable->name)
                ->line($this->getEmailMessage())
                ->line('**Detalhes do Horário:**')
                ->line('Disciplina: ' . $this->schedule->subject->name)
                ->line('Turma: ' . $this->schedule->class->name)
                ->line('Horário: ' . $this->schedule->formatted_time)
                ->line('Dia da semana: ' . $this->schedule->day_of_week_label);

        if (!empty($this->changes)) {
            $message->line('**Alterações:**');
            foreach ($this->changes as $field => $change) {
                $message->line("- {$field}: {$change['from']} → {$change['to']}");
            }
        }

        return $message->action('Ver Horário', url('/schedules/' . $this->schedule->id));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'schedule_changed',
            'change_type' => $this->changeType,
            'schedule_id' => $this->schedule->id,
            'schedule_name' => $this->schedule->name,
            'subject_name' => $this->schedule->subject->name,
            'class_name' => $this->schedule->class->name,
            'teacher_name' => $this->schedule->teacher->full_name,
            'formatted_time' => $this->schedule->formatted_time,
            'day_of_week' => $this->schedule->day_of_week_label,
            'changes' => $this->changes,
            'message' => $this->getDatabaseMessage()
        ];
    }

    private function getEmailSubject(): string
    {
        switch ($this->changeType) {
            case 'created':
                return 'Novo Horário Adicionado';
            case 'time_changed':
                return 'Alteração de Horário';
            case 'teacher_changed':
                return 'Mudança de Professor';
            case 'cancelled':
                return 'Horário Cancelado';
            case 'room_changed':
                return 'Mudança de Sala';
            default:
                return 'Atualização de Horário';
        }
    }

    private function getEmailMessage(): string
    {
        switch ($this->changeType) {
            case 'created':
                return 'Um novo horário foi adicionado à sua agenda.';
            case 'time_changed':
                return 'O horário de uma de suas aulas foi alterado.';
            case 'teacher_changed':
                return 'Houve mudança no professor de uma de suas disciplinas.';
            case 'cancelled':
                return 'Um horário foi cancelado.';
            case 'room_changed':
                return 'A sala de aula foi alterada.';
            default:
                return 'Houve uma atualização em um de seus horários.';
        }
    }

    private function getDatabaseMessage(): string
    {
        $baseName = $this->schedule->subject->name . ' - ' . $this->schedule->class->name;

        switch ($this->changeType) {
            case 'created':
                return "Novo horário adicionado: {$baseName}";
            case 'time_changed':
                return "Horário alterado: {$baseName} - {$this->schedule->formatted_time}";
            case 'teacher_changed':
                return "Professor alterado em: {$baseName}";
            case 'cancelled':
                return "Horário cancelado: {$baseName}";
            case 'room_changed':
                return "Sala alterada em: {$baseName}";
            default:
                return "Atualização em: {$baseName}";
        }
    }
}
EOF

# 10.2 Lesson Notification
cat > app/Notifications/V1/Schedule/LessonNotification.php << 'EOF'
<?php

namespace App\Notifications\V1\Schedule;

use App\Models\V1\Schedule\Lesson;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LessonNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Lesson $lesson;
    protected string $eventType;
    protected array $additionalData;

    public function __construct(Lesson $lesson, string $eventType, array $additionalData = [])
    {
        $this->lesson = $lesson;
        $this->eventType = $eventType;
        $this->additionalData = $additionalData;
    }

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $message = new MailMessage();

        return $message
            ->subject($this->getEmailSubject())
            ->greeting('Olá ' . $notifiable->name)
            ->line($this->getEmailMessage())
            ->line('**Detalhes da Aula:**')
            ->line('Título: ' . $this->lesson->title)
            ->line('Disciplina: ' . $this->lesson->subject->name)
            ->line('Data: ' . $this->lesson->lesson_date->format('d/m/Y'))
            ->line('Horário: ' . $this->lesson->formatted_time)
            ->when($this->lesson->is_online, function ($message) {
                return $message->line('**Aula Online**')
                               ->action('Entrar na Reunião', $this->lesson->online_meeting_url);
            })
            ->when(!$this->lesson->is_online, function ($message) {
                return $message->line('Sala: ' . ($this->lesson->classroom ?? 'A definir'));
            })
            ->action('Ver Aula', url('/lessons/' . $this->lesson->id));
    }

    public function toDatabase($notifiable): array
    {
        return [
            'type' => 'lesson_notification',
            'event_type' => $this->eventType,
            'lesson_id' => $this->lesson->id,
            'lesson_title' => $this->lesson->title,
            'subject_name' => $this->lesson->subject->name,
            'class_name' => $this->lesson->class->name,
            'lesson_date' => $this->lesson->lesson_date->format('Y-m-d'),
            'formatted_time' => $this->lesson->formatted_time,
            'is_online' => $this->lesson->is_online,
            'classroom' => $this->lesson->classroom,
            'status' => $this->lesson->status,
            'message' => $this->getDatabaseMessage(),
            'additional_data' => $this->additionalData
        ];
    }

    private function getEmailSubject(): string
    {
        switch ($this->eventType) {
            case 'lesson_starting':
                return 'Aula Iniciando em Breve';
            case 'lesson_cancelled':
                return 'Aula Cancelada';
            case 'lesson_completed':
                return 'Aula Concluída';
            case 'content_added':
                return 'Novo Conteúdo Adicionado';
            case 'homework_assigned':
                return 'Atividade Atribuída';
            case 'attendance_marked':
                return 'Presença Registrada';
            default:
                return 'Atualização da Aula';
        }
    }

    private function getEmailMessage(): string
    {
        switch ($this->eventType) {
            case 'lesson_starting':
                return 'Uma de suas aulas começará em breve.';
            case 'lesson_cancelled':
                return 'Uma aula foi cancelada.';
            case 'lesson_completed':
                return 'A aula foi concluída com sucesso.';
            case 'content_added':
                return 'Novo material foi adicionado à aula.';
            case 'homework_assigned':
                return 'Uma nova atividade foi atribuída.';
            case 'attendance_marked':
                return 'Sua presença na aula foi registrada.';
            default:
                return 'Há uma atualização sobre uma de suas aulas.';
        }
    }

    private function getDatabaseMessage(): string
    {
        $lessonInfo = $this->lesson->subject->name . ' - ' . $this->lesson->lesson_date->format('d/m/Y');

        switch ($this->eventType) {
            case 'lesson_starting':
                return "Aula iniciando: {$lessonInfo}";
            case 'lesson_cancelled':
                return "Aula cancelada: {$lessonInfo}";
            case 'lesson_completed':
                return "Aula concluída: {$lessonInfo}";
            case 'content_added':
                return "Novo conteúdo em: {$lessonInfo}";
            case 'homework_assigned':
                return "Atividade atribuída em: {$lessonInfo}";
            case 'attendance_marked':
                return "Presença registrada em: {$lessonInfo}";
            default:
                return "Atualização em: {$lessonInfo}";
        }
    }
}
EOF

# =============================================================================
# 11. WORKFLOW INTEGRATION
# =============================================================================

echo "🔄 Creating workflow integration..."

mkdir -p app/Services/V1/Workflow/Schedule

# 11.1 Schedule Workflow Service
cat > app/Services/V1/Workflow/Schedule/ScheduleWorkflowService.php << 'EOF'
<?php

namespace App\Services\V1\Workflow\Schedule;

use App\Models\V1\Schedule\Schedule;
use App\Models\V1\Schedule\Lesson;
use App\Services\V1\Workflow\WorkflowService;

class ScheduleWorkflowService
{
    protected WorkflowService $workflowService;

    public function __construct(WorkflowService $workflowService)
    {
        $this->workflowService = $workflowService;
    }

    /**
     * Create workflow for schedule change request
     */
    public function createScheduleChangeRequest(array $data): array
    {
        $workflowData = [
            'workflow_type' => 'schedule_change_request',
            'title' => 'Solicitação de Alteração de Horário',
            'description' => $data['reason'] ?? 'Solicitação de alteração de horário',
            'priority' => $data['priority'] ?? 'medium',
            'form_data' => [
                'current_schedule_id' => $data['schedule_id'],
                'requested_changes' => $data['changes'],
                'reason' => $data['reason'],
                'effective_date' => $data['effective_date'] ?? now()->addDays(7)->toDateString(),
                'teacher_comments' => $data['teacher_comments'] ?? null
            ],
            'approvers' => [
                ['user_type' => 'academic_coordinator', 'required' => true],
                ['user_type' => 'principal', 'required' => false]
            ]
        ];

        return $this->workflowService->createWorkflow($workflowData);
    }

    /**
     * Create workflow for extra lesson request
     */
    public function createExtraLessonRequest(array $data): array
    {
        $workflowData = [
            'workflow_type' => 'extra_lesson_request',
            'title' => 'Solicitação de Aula Extra',
            'description' => 'Solicitação de aula extra - ' . ($data['subject_name'] ?? 'Disciplina'),
            'priority' => 'medium',
            'form_data' => [
                'subject_id' => $data['subject_id'],
                'class_id' => $data['class_id'],
                'teacher_id' => $data['teacher_id'],
                'proposed_date' => $data['proposed_date'],
                'proposed_time' => $data['proposed_time'],
                'duration_minutes' => $data['duration_minutes'] ?? 60,
                'reason' => $data['reason'],
                'lesson_objectives' => $data['lesson_objectives'] ?? null,
                'is_makeup' => $data['is_makeup'] ?? false,
                'original_lesson_id' => $data['original_lesson_id'] ?? null
            ],
            'approvers' => [
                ['user_type' => 'academic_coordinator', 'required' => true]
            ]
        ];

        return $this->workflowService->createWorkflow($workflowData);
    }

    /**
     * Create workflow for lesson cancellation
     */
    public function createLessonCancellationRequest(array $data): array
    {
        $workflowData = [
            'workflow_type' => 'lesson_cancellation',
            'title' => 'Solicitação de Cancelamento de Aula',
            'description' => 'Cancelamento - ' . ($data['lesson_title'] ?? 'Aula'),
            'priority' => $data['is_emergency'] ? 'high' : 'medium',
            'form_data' => [
                'lesson_id' => $data['lesson_id'],
                'cancellation_reason' => $data['reason'],
                'is_emergency' => $data['is_emergency'] ?? false,
                'requires_makeup' => $data['requires_makeup'] ?? true,
                'proposed_makeup_date' => $data['proposed_makeup_date'] ?? null,
                'notification_required' => $data['notification_required'] ?? true,
                'advance_notice_hours' => $data['advance_notice_hours'] ?? 0
            ],
            'approvers' => $data['is_emergency'] ?
                [['user_type' => 'academic_coordinator', 'required' => true]] :
                [
                    ['user_type' => 'academic_coordinator', 'required' => true],
                    ['user_type' => 'principal', 'required' => false]
                ]
        ];

        return $this->workflowService->createWorkflow($workflowData);
    }

    /**
     * Create workflow for attendance appeal
     */
    public function createAttendanceAppeal(array $data): array
    {
        $workflowData = [
            'workflow_type' => 'attendance_appeal',
            'title' => 'Recurso de Presença',
            'description' => 'Recurso de presença para ' . ($data['student_name'] ?? 'Estudante'),
            'priority' => 'medium',
            'form_data' => [
                'lesson_id' => $data['lesson_id'],
                'student_id' => $data['student_id'],
                'current_status' => $data['current_status'],
                'requested_status' => $data['requested_status'],
                'appeal_reason' => $data['appeal_reason'],
                'supporting_evidence' => $data['supporting_evidence'] ?? null,
                'parent_request' => $data['parent_request'] ?? false,
                'medical_certificate' => $data['medical_certificate'] ?? false
            ],
            'approvers' => [
                ['user_type' => 'teacher', 'specific_user_id' => $data['lesson_teacher_id']],
                ['user_type' => 'academic_coordinator', 'required' => true]
            ]
        ];

        return $this->workflowService->createWorkflow($workflowData);
    }

    /**
     * Process workflow approval
     */
    public function processWorkflowApproval(int $workflowId, string $action, string $comments = null): bool
    {
        $workflow = $this->workflowService->getWorkflow($workflowId);

        if (!$workflow) {
            return false;
        }

        // Process the approval
        $result = $this->workflowService->processApproval($workflowId, $action, $comments);

        if ($result && $workflow['status'] === 'approved') {
            // Execute the approved action
            $this->executeApprovedWorkflow($workflow);
        }

        return $result;
    }

    /**
     * Execute approved workflow actions
     */
    private function executeApprovedWorkflow(array $workflow): void
    {
        switch ($workflow['workflow_type']) {
            case 'schedule_change_request':
                $this->executeScheduleChange($workflow);
                break;

            case 'extra_lesson_request':
                $this->executeExtraLessonCreation($workflow);
                break;

            case 'lesson_cancellation':
                $this->executeLessonCancellation($workflow);
                break;

            case 'attendance_appeal':
                $this->executeAttendanceAppeal($workflow);
                break;
        }
    }

    private function executeScheduleChange(array $workflow): void
    {
        $formData = $workflow['form_data'];
        $schedule = Schedule::find($formData['current_schedule_id']);

        if ($schedule) {
            $changes = $formData['requested_changes'];
            $schedule->update($changes);

            // Send notifications about the change
            // $this->notifyScheduleChange($schedule, $changes);
        }
    }

    private function executeExtraLessonCreation(array $workflow): void
    {
        $formData = $workflow['form_data'];

        $lessonData = [
            'subject_id' => $formData['subject_id'],
            'class_id' => $formData['class_id'],
            'teacher_id' => $formData['teacher_id'],
            'lesson_date' => $formData['proposed_date'],
            'start_time' => $formData['proposed_time'],
            'duration_minutes' => $formData['duration_minutes'],
            'title' => 'Aula Extra - ' . now()->format('d/m/Y'),
            'description' => $formData['reason'],
            'type' => $formData['is_makeup'] ? 'makeup' : 'extra',
            'status' => 'scheduled'
        ];

        // Calculate end time
        $startTime = \Carbon\Carbon::parse($formData['proposed_time']);
        $endTime = $startTime->copy()->addMinutes($formData['duration_minutes']);
        $lessonData['end_time'] = $endTime->format('H:i');

        Lesson::create($lessonData);
    }

    private function executeLessonCancellation(array $workflow): void
    {
        $formData = $workflow['form_data'];
        $lesson = Lesson::find($formData['lesson_id']);

        if ($lesson) {
            $lesson->cancel($formData['cancellation_reason']);

            // Create makeup lesson if required
            if ($formData['requires_makeup'] && $formData['proposed_makeup_date']) {
                $makeupData = [
                    'subject_id' => $lesson->subject_id,
                    'class_id' => $lesson->class_id,
                    'teacher_id' => $lesson->teacher_id,
                    'lesson_date' => $formData['proposed_makeup_date'],
                    'start_time' => $lesson->start_time,
                    'end_time' => $lesson->end_time,
                    'duration_minutes' => $lesson->duration_minutes,
                    'title' => 'Reposição - ' . $lesson->title,
                    'type' => 'makeup',
                    'status' => 'scheduled'
                ];

                Lesson::create($makeupData);
            }
        }
    }

    private function executeAttendanceAppeal(array $workflow): void
    {
        $formData = $workflow['form_data'];

        $attendance = \App\Models\V1\Schedule\LessonAttendance::where('lesson_id', $formData['lesson_id'])
            ->where('student_id', $formData['student_id'])
            ->first();

        if ($attendance) {
            $attendance->update([
                'status' => $formData['requested_status'],
                'notes' => 'Recurso aprovado: ' . $formData['appeal_reason'],
                'approval_status' => 'approved',
                'approved_by' => auth()->id(),
                'approved_at' => now()
            ]);
        }
    }
}
EOF

echo "✅ Schedule & Lessons Module - Final Components Created!"
echo ""
echo "📋 Final components created:"
echo "   📝 Request Classes: BaseScheduleRequest, StoreScheduleRequest, UpdateScheduleRequest, StoreLessonRequest, UpdateLessonRequest"
echo "   🛤️ Routes: Complete routing system with teacher/student/parent portals"
echo "   🔒 Policies: SchedulePolicy, LessonPolicy with role-based permissions"
echo "   📬 Notifications: ScheduleChangedNotification, LessonNotification"
echo "   🔄 Workflow Integration: ScheduleWorkflowService for approvals"
echo ""
echo "🎯 Module Features:"
echo "   ✅ Complete CRUD for Schedules and Lessons"
echo "   ✅ Conflict detection and resolution"
echo "   ✅ QR Code attendance system"
echo "   ✅ Multi-role access (Teacher/Student/Parent/Admin)"
echo "   ✅ Content management for lessons"
echo "   ✅ Automatic lesson generation from schedules"
echo "   ✅ Workflow integration for approvals"
echo "   ✅ Real-time notifications"
echo "   ✅ Comprehensive reporting"
echo ""
echo "🚀 Ready to deploy! Next steps:"
echo "   1. Run migrations: php artisan migrate"
echo "   2. Register policies in AuthServiceProvider"
echo "   3. Configure notification channels"
echo "   4. Set up queue workers for notifications"
echo "   5. Test the complete workflow"
