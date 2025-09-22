#!/bin/bash

# iEDU Schedule & Lessons Management Module
# Módulo completo de Gestão de Horários e Aulas
# Criação de migrations, models, services, controllers, etc.

echo "🏫 Creating Schedule & Lessons Management Module..."

# Generate sequential timestamps
CURRENT_TIME=$(date +%s)
MIGRATION_1=$(date -d "@$((CURRENT_TIME + 1))" +%Y_%m_%d_%H%M%S)
MIGRATION_2=$(date -d "@$((CURRENT_TIME + 2))" +%Y_%m_%d_%H%M%S)
MIGRATION_3=$(date -d "@$((CURRENT_TIME + 3))" +%Y_%m_%d_%H%M%S)
MIGRATION_4=$(date -d "@$((CURRENT_TIME + 4))" +%Y_%m_%d_%H%M%S)
MIGRATION_5=$(date -d "@$((CURRENT_TIME + 5))" +%Y_%m_%d_%H%M%S)

# Validate Laravel root
if [ ! -d "vendor" ]; then
    echo "❌ Error: Please run this script from the Laravel root directory"
    exit 1
fi

# =============================================================================
# 1. DATABASE MIGRATIONS
# =============================================================================

echo "📋 Creating database migrations..."

# 1.1 Schedules Table
cat > "database/migrations/${MIGRATION_1}_create_schedules_table.php" << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('academic_year_id')->constrained('academic_years')->onDelete('cascade');
            $table->foreignId('academic_term_id')->nullable()->constrained('academic_terms')->onDelete('set null');

            // Basic Schedule Information
            $table->string('name'); // "Matemática - 7º A - Manhã"
            $table->text('description')->nullable();

            // Associations
            $table->foreignId('subject_id')->constrained('subjects')->onDelete('cascade');
            $table->foreignId('class_id')->constrained('classes')->onDelete('cascade');
            $table->foreignId('teacher_id')->constrained('teachers')->onDelete('cascade');
            $table->string('classroom', 50)->nullable(); // Sala de aula

            // Time Configuration
            $table->enum('period', ['morning', 'afternoon', 'evening', 'night'])->default('morning');
            $table->enum('day_of_week', ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday']);
            $table->time('start_time');
            $table->time('end_time');

            // Recurrence Configuration
            $table->date('start_date');
            $table->date('end_date');
            $table->json('recurrence_pattern')->nullable(); // Weekly, biweekly, etc.

            // Status and Configuration
            $table->enum('status', ['active', 'suspended', 'cancelled', 'completed'])->default('active');
            $table->boolean('is_online')->default(false);
            $table->string('online_meeting_url', 500)->nullable();
            $table->json('configuration_json')->nullable(); // Additional settings

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();

            // Indexes
            $table->index(['school_id', 'academic_year_id']);
            $table->index(['teacher_id', 'day_of_week', 'start_time']);
            $table->index(['class_id', 'subject_id']);
            $table->index(['day_of_week', 'period']);
            $table->index(['start_date', 'end_date']);
            $table->index('status');

            // Unique constraint to prevent conflicts
            $table->unique([
                'school_id', 'teacher_id', 'day_of_week',
                'start_time', 'end_time', 'start_date', 'end_date'
            ], 'unique_teacher_schedule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedules');
    }
};
EOF

# 1.2 Lessons Table
cat > "database/migrations/${MIGRATION_2}_create_lessons_table.php" << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('schedule_id')->constrained('schedules')->onDelete('cascade');

            // Lesson Details
            $table->string('title'); // "Equações de 2º grau"
            $table->text('description')->nullable();
            $table->json('objectives')->nullable(); // Learning objectives

            // Associations
            $table->foreignId('subject_id')->constrained('subjects');
            $table->foreignId('class_id')->constrained('classes');
            $table->foreignId('teacher_id')->constrained('teachers');
            $table->foreignId('academic_term_id')->constrained('academic_terms');

            // Timing
            $table->date('lesson_date');
            $table->time('start_time');
            $table->time('end_time');
            $table->integer('duration_minutes'); // Calculated field

            // Location and Format
            $table->string('classroom', 50)->nullable();
            $table->boolean('is_online')->default(false);
            $table->string('online_meeting_url', 500)->nullable();
            $table->json('online_meeting_details')->nullable(); // Zoom ID, password, etc.

            // Status and Progress
            $table->enum('status', [
                'scheduled', 'in_progress', 'completed',
                'cancelled', 'postponed', 'absent_teacher'
            ])->default('scheduled');
            $table->enum('type', [
                'regular', 'makeup', 'extra', 'review',
                'exam', 'practical', 'field_trip'
            ])->default('regular');

            // Content and Curriculum
            $table->text('content_summary')->nullable(); // What was taught
            $table->json('curriculum_topics')->nullable(); // Topics covered
            $table->text('homework_assigned')->nullable();
            $table->date('homework_due_date')->nullable();

            // Attendance and Participation
            $table->integer('expected_students')->default(0);
            $table->integer('present_students')->default(0);
            $table->decimal('attendance_rate', 5, 2)->nullable(); // Calculated

            // Teacher Notes and Observations
            $table->text('teacher_notes')->nullable();
            $table->text('lesson_observations')->nullable();
            $table->json('student_participation')->nullable(); // Individual observations

            // Workflow Integration
            $table->boolean('requires_approval')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();

            // Audit
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();

            // Indexes
            $table->index(['school_id', 'lesson_date']);
            $table->index(['teacher_id', 'lesson_date']);
            $table->index(['class_id', 'lesson_date']);
            $table->index(['subject_id', 'lesson_date']);
            $table->index(['academic_term_id']);
            $table->index(['status', 'type']);
            $table->index(['lesson_date', 'start_time']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lessons');
    }
};
EOF

# 1.3 Lesson Contents Table
cat > "database/migrations/${MIGRATION_3}_create_lesson_contents_table.php" << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');

            // Content Details
            $table->string('title');
            $table->text('description')->nullable();
            $table->enum('content_type', [
                'document', 'video', 'audio', 'link', 'image',
                'presentation', 'worksheet', 'quiz', 'assignment',
                'meeting_recording', 'live_stream', 'external_resource'
            ]);

            // File Information (for uploaded content)
            $table->string('file_name')->nullable();
            $table->string('file_path')->nullable();
            $table->string('file_type', 10)->nullable(); // pdf, docx, mp4, etc.
            $table->unsignedBigInteger('file_size')->nullable(); // in bytes
            $table->string('mime_type', 100)->nullable();

            // URL Information (for links and external resources)
            $table->text('url')->nullable();
            $table->string('thumbnail_url', 500)->nullable();
            $table->json('embed_data')->nullable(); // YouTube embed, etc.

            // Content Organization
            $table->string('category', 100)->nullable(); // "Required Reading", "Supplementary", etc.
            $table->integer('sort_order')->default(0);
            $table->boolean('is_required')->default(false);
            $table->boolean('is_downloadable')->default(true);

            // Access Control
            $table->boolean('is_public')->default(false); // Visible to parents/guardians
            $table->json('access_permissions')->nullable(); // Custom permissions
            $table->date('available_from')->nullable();
            $table->date('available_until')->nullable();

            // Metadata
            $table->json('metadata')->nullable(); // Duration, size, etc.
            $table->text('notes')->nullable();
            $table->json('tags')->nullable();

            // Audit
            $table->foreignId('uploaded_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();

            // Indexes
            $table->index(['lesson_id', 'content_type']);
            $table->index(['school_id', 'content_type']);
            $table->index(['sort_order']);
            $table->index(['is_required', 'is_public']);
            $table->index(['available_from', 'available_until']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_contents');
    }
};
EOF

# 1.4 Lesson Attendances Table
cat > "database/migrations/${MIGRATION_4}_create_lesson_attendances_table.php" << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lesson_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');
            $table->foreignId('lesson_id')->constrained('lessons')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('students')->onDelete('cascade');

            // Attendance Status
            $table->enum('status', [
                'present', 'absent', 'late', 'excused',
                'left_early', 'partial', 'online_present'
            ])->default('present');

            // Timing Information
            $table->time('arrival_time')->nullable();
            $table->time('departure_time')->nullable();
            $table->integer('minutes_late')->nullable(); // Calculated
            $table->integer('minutes_present')->nullable(); // For partial attendance

            // Attendance Method
            $table->enum('marked_by_method', [
                'teacher_manual', 'qr_code', 'student_self_checkin',
                'automatic_online', 'biometric', 'rfid'
            ])->default('teacher_manual');

            // Additional Information
            $table->text('notes')->nullable(); // Reason for absence, etc.
            $table->boolean('notified_parent')->default(false);
            $table->timestamp('parent_notified_at')->nullable();

            // Location Data (for verification)
            $table->decimal('check_in_latitude', 10, 8)->nullable();
            $table->decimal('check_in_longitude', 11, 8)->nullable();
            $table->string('device_info')->nullable();
            $table->string('ip_address', 45)->nullable();

            // Workflow Integration
            $table->boolean('requires_approval')->default(false);
            $table->enum('approval_status', ['pending', 'approved', 'rejected'])->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users');
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_notes')->nullable();

            // Audit
            $table->foreignId('marked_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();

            // Unique constraint
            $table->unique(['lesson_id', 'student_id']);

            // Indexes
            $table->index(['school_id', 'lesson_id']);
            $table->index(['student_id', 'status']);
            $table->index(['lesson_id', 'status']);
            $table->index(['marked_by_method']);
            $table->index(['requires_approval', 'approval_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_attendances');
    }
};
EOF

# 1.5 Schedule Conflicts Table
cat > "database/migrations/${MIGRATION_5}_create_schedule_conflicts_table.php" << 'EOF'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('schedule_conflicts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('school_id')->constrained('schools')->onDelete('cascade');

            // Conflict Details
            $table->enum('conflict_type', [
                'teacher_double_booking', 'classroom_double_booking',
                'student_schedule_overlap', 'resource_conflict',
                'time_constraint_violation', 'capacity_exceeded'
            ]);
            $table->string('conflict_description');

            // Related Schedules
            $table->json('conflicting_schedule_ids'); // Array of schedule IDs
            $table->json('affected_entities'); // Teachers, classrooms, students affected

            // Conflict Details
            $table->date('conflict_date');
            $table->time('conflict_start_time');
            $table->time('conflict_end_time');
            $table->enum('severity', ['low', 'medium', 'high', 'critical'])->default('medium');

            // Resolution
            $table->enum('status', ['detected', 'acknowledged', 'resolved', 'ignored'])->default('detected');
            $table->text('resolution_notes')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            $table->timestamp('resolved_at')->nullable();

            // Detection Information
            $table->enum('detection_method', ['automatic', 'manual', 'report'])->default('automatic');
            $table->foreignId('detected_by')->nullable()->constrained('users');

            $table->timestamps();

            // Indexes
            $table->index(['school_id', 'conflict_type']);
            $table->index(['conflict_date', 'status']);
            $table->index(['severity', 'status']);
            $table->index(['detection_method']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_conflicts');
    }
};
EOF

# =============================================================================
# 2. MODELS
# =============================================================================

echo "🎯 Creating models..."

mkdir -p app/Models/V1/Schedule

# 2.1 Schedule Model
cat > app/Models/V1/Schedule/Schedule.php << 'EOF'
<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\School;
use App\Models\User;
use App\Models\V1\Academic\Subject;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\Academic\AcademicYear;
use App\Models\V1\Academic\AcademicTerm;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Schedule extends BaseModel
{
    protected $fillable = [
        'school_id', 'academic_year_id', 'academic_term_id',
        'name', 'description',
        'subject_id', 'class_id', 'teacher_id', 'classroom',
        'period', 'day_of_week', 'start_time', 'end_time',
        'start_date', 'end_date', 'recurrence_pattern',
        'status', 'is_online', 'online_meeting_url', 'configuration_json',
        'created_by', 'updated_by'
    ];

    protected $casts = [
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'start_date' => 'date',
        'end_date' => 'date',
        'recurrence_pattern' => 'array',
        'configuration_json' => 'array',
        'is_online' => 'boolean'
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class);
    }

    // Scopes
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeByPeriod(Builder $query, string $period): Builder
    {
        return $query->where('period', $period);
    }

    public function scopeByDay(Builder $query, string $day): Builder
    {
        return $query->where('day_of_week', $day);
    }

    public function scopeByTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeByClass(Builder $query, int $classId): Builder
    {
        return $query->where('class_id', $classId);
    }

    public function scopeByTimeRange(Builder $query, string $startTime, string $endTime): Builder
    {
        return $query->where(function ($q) use ($startTime, $endTime) {
            $q->whereBetween('start_time', [$startTime, $endTime])
              ->orWhereBetween('end_time', [$startTime, $endTime])
              ->orWhere(function ($q2) use ($startTime, $endTime) {
                  $q2->where('start_time', '<=', $startTime)
                     ->where('end_time', '>=', $endTime);
              });
        });
    }

    public function scopeConflictsWith(Builder $query, int $teacherId, string $dayOfWeek, string $startTime, string $endTime): Builder
    {
        return $query->where('teacher_id', $teacherId)
                     ->where('day_of_week', $dayOfWeek)
                     ->where('status', 'active')
                     ->where(function ($q) use ($startTime, $endTime) {
                         $q->whereBetween('start_time', [$startTime, $endTime])
                           ->orWhereBetween('end_time', [$startTime, $endTime])
                           ->orWhere(function ($q2) use ($startTime, $endTime) {
                               $q2->where('start_time', '<=', $startTime)
                                  ->where('end_time', '>=', $endTime);
                           });
                     });
    }

    // Accessors & Methods
    public function getDurationInMinutesAttribute(): int
    {
        $start = Carbon::parse($this->start_time);
        $end = Carbon::parse($this->end_time);
        return $end->diffInMinutes($start);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isOnline(): bool
    {
        return $this->is_online;
    }

    public function hasConflictWith(Schedule $otherSchedule): bool
    {
        if ($this->teacher_id !== $otherSchedule->teacher_id ||
            $this->day_of_week !== $otherSchedule->day_of_week) {
            return false;
        }

        $thisStart = Carbon::parse($this->start_time);
        $thisEnd = Carbon::parse($this->end_time);
        $otherStart = Carbon::parse($otherSchedule->start_time);
        $otherEnd = Carbon::parse($otherSchedule->end_time);

        return $thisStart->lt($otherEnd) && $thisEnd->gt($otherStart);
    }

    public function generateLessons(): array
    {
        $lessons = [];
        $currentDate = Carbon::parse($this->start_date);
        $endDate = Carbon::parse($this->end_date);

        while ($currentDate->lte($endDate)) {
            if ($currentDate->dayOfWeek === $this->getDayOfWeekNumber()) {
                $lessons[] = [
                    'schedule_id' => $this->id,
                    'school_id' => $this->school_id,
                    'subject_id' => $this->subject_id,
                    'class_id' => $this->class_id,
                    'teacher_id' => $this->teacher_id,
                    'academic_term_id' => $this->academic_term_id,
                    'lesson_date' => $currentDate->toDateString(),
                    'start_time' => $this->start_time,
                    'end_time' => $this->end_time,
                    'duration_minutes' => $this->duration_in_minutes,
                    'classroom' => $this->classroom,
                    'is_online' => $this->is_online,
                    'online_meeting_url' => $this->online_meeting_url,
                    'title' => $this->name,
                    'status' => 'scheduled',
                    'type' => 'regular',
                    'created_by' => $this->created_by,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }
            $currentDate->addDay();
        }

        return $lessons;
    }

    private function getDayOfWeekNumber(): int
    {
        $days = [
            'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
            'thursday' => 4, 'friday' => 5, 'saturday' => 6
        ];
        return $days[$this->day_of_week] ?? 1;
    }

    public function getFormattedTimeAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('H:i') .
               ' - ' .
               Carbon::parse($this->end_time)->format('H:i');
    }

    public function getPeriodLabelAttribute(): string
    {
        $labels = [
            'morning' => 'Manhã',
            'afternoon' => 'Tarde',
            'evening' => 'Noite',
            'night' => 'Madrugada'
        ];
        return $labels[$this->period] ?? ucfirst($this->period);
    }

    public function getDayOfWeekLabelAttribute(): string
    {
        $labels = [
            'monday' => 'Segunda-feira',
            'tuesday' => 'Terça-feira',
            'wednesday' => 'Quarta-feira',
            'thursday' => 'Quinta-feira',
            'friday' => 'Sexta-feira',
            'saturday' => 'Sábado',
            'sunday' => 'Domingo'
        ];
        return $labels[$this->day_of_week] ?? ucfirst($this->day_of_week);
    }
}
EOF

# 2.2 Lesson Model
cat > app/Models/V1/Schedule/Lesson.php << 'EOF'
<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\School;
use App\Models\User;
use App\Models\Student;
use App\Models\V1\Academic\Subject;
use App\Models\V1\Academic\AcademicClass;
use App\Models\V1\Academic\Teacher;
use App\Models\V1\Academic\AcademicTerm;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class Lesson extends BaseModel
{
    protected $fillable = [
        'school_id', 'schedule_id',
        'title', 'description', 'objectives',
        'subject_id', 'class_id', 'teacher_id', 'academic_term_id',
        'lesson_date', 'start_time', 'end_time', 'duration_minutes',
        'classroom', 'is_online', 'online_meeting_url', 'online_meeting_details',
        'status', 'type',
        'content_summary', 'curriculum_topics', 'homework_assigned', 'homework_due_date',
        'expected_students', 'present_students', 'attendance_rate',
        'teacher_notes', 'lesson_observations', 'student_participation',
        'requires_approval', 'approved_by', 'approved_at',
        'created_by', 'updated_by'
    ];

    protected $casts = [
        'lesson_date' => 'date',
        'start_time' => 'datetime:H:i:s',
        'end_time' => 'datetime:H:i:s',
        'homework_due_date' => 'date',
        'objectives' => 'array',
        'online_meeting_details' => 'array',
        'curriculum_topics' => 'array',
        'student_participation' => 'array',
        'is_online' => 'boolean',
        'requires_approval' => 'boolean',
        'approved_at' => 'datetime',
        'attendance_rate' => 'decimal:2',
        'duration_minutes' => 'integer',
        'expected_students' => 'integer',
        'present_students' => 'integer'
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(Schedule::class);
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function class(): BelongsTo
    {
        return $this->belongsTo(AcademicClass::class, 'class_id');
    }

    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Teacher::class);
    }

    public function academicTerm(): BelongsTo
    {
        return $this->belongsTo(AcademicTerm::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(LessonContent::class)->orderBy('sort_order');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(LessonAttendance::class);
    }

    // Scopes
    public function scopeScheduled(Builder $query): Builder
    {
        return $query->where('status', 'scheduled');
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', 'completed');
    }

    public function scopeCancelled(Builder $query): Builder
    {
        return $query->where('status', 'cancelled');
    }

    public function scopeByDate(Builder $query, string $date): Builder
    {
        return $query->where('lesson_date', $date);
    }

    public function scopeByDateRange(Builder $query, string $startDate, string $endDate): Builder
    {
        return $query->whereBetween('lesson_date', [$startDate, $endDate]);
    }

    public function scopeByTeacher(Builder $query, int $teacherId): Builder
    {
        return $query->where('teacher_id', $teacherId);
    }

    public function scopeByClass(Builder $query, int $classId): Builder
    {
        return $query->where('class_id', $classId);
    }

    public function scopeBySubject(Builder $query, int $subjectId): Builder
    {
        return $query->where('subject_id', $subjectId);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->where('lesson_date', '>=', now()->toDateString())
                     ->whereIn('status', ['scheduled', 'in_progress'])
                     ->orderBy('lesson_date')
                     ->orderBy('start_time');
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->where('lesson_date', now()->toDateString());
    }

    public function scopeThisWeek(Builder $query): Builder
    {
        return $query->whereBetween('lesson_date', [
            now()->startOfWeek()->toDateString(),
            now()->endOfWeek()->toDateString()
        ]);
    }

    // Methods
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    public function isOnline(): bool
    {
        return $this->is_online;
    }

    public function isToday(): bool
    {
        return $this->lesson_date->isToday();
    }

    public function isPast(): bool
    {
        return $this->lesson_date->isPast();
    }

    public function isFuture(): bool
    {
        return $this->lesson_date->isFuture();
    }

    public function hasHomework(): bool
    {
        return !empty($this->homework_assigned);
    }

    public function hasContents(): bool
    {
        return $this->contents()->exists();
    }

    public function calculateAttendanceRate(): float
    {
        if ($this->expected_students == 0) return 0;
        return ($this->present_students / $this->expected_students) * 100;
    }

    public function updateAttendanceStats(): void
    {
        $totalStudents = $this->class->current_enrollment;
        $presentCount = $this->attendances()->where('status', 'present')->count();

        $this->update([
            'expected_students' => $totalStudents,
            'present_students' => $presentCount,
            'attendance_rate' => $totalStudents > 0 ? ($presentCount / $totalStudents) * 100 : 0
        ]);
    }

    public function markAsCompleted(array $data = []): bool
    {
        $this->updateAttendanceStats();

        return $this->update(array_merge([
            'status' => 'completed',
        ], $data));
    }

    public function cancel(string $reason = null): bool
    {
        return $this->update([
            'status' => 'cancelled',
            'teacher_notes' => $this->teacher_notes . "\n\nCancelamento: " . ($reason ?? 'Não especificado')
        ]);
    }

    public function getFormattedTimeAttribute(): string
    {
        return Carbon::parse($this->start_time)->format('H:i') .
               ' - ' .
               Carbon::parse($this->end_time)->format('H:i');
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'scheduled' => 'Agendada',
            'in_progress' => 'Em andamento',
            'completed' => 'Concluída',
            'cancelled' => 'Cancelada',
            'postponed' => 'Adiada',
            'absent_teacher' => 'Professor ausente'
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'regular' => 'Regular',
            'makeup' => 'Reposição',
            'extra' => 'Extra',
            'review' => 'Revisão',
            'exam' => 'Avaliação',
            'practical' => 'Prática',
            'field_trip' => 'Excursão'
        ];
        return $labels[$this->type] ?? ucfirst($this->type);
    }
}
EOF

# 2.3 Lesson Content Model
cat > app/Models/V1/Schedule/LessonContent.php << 'EOF'
<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class LessonContent extends BaseModel
{
    protected $fillable = [
        'school_id', 'lesson_id',
        'title', 'description', 'content_type',
        'file_name', 'file_path', 'file_type', 'file_size', 'mime_type',
        'url', 'thumbnail_url', 'embed_data',
        'category', 'sort_order', 'is_required', 'is_downloadable',
        'is_public', 'access_permissions', 'available_from', 'available_until',
        'metadata', 'notes', 'tags',
        'uploaded_by', 'updated_by'
    ];

    protected $casts = [
        'embed_data' => 'array',
        'access_permissions' => 'array',
        'available_from' => 'date',
        'available_until' => 'date',
        'metadata' => 'array',
        'tags' => 'array',
        'is_required' => 'boolean',
        'is_downloadable' => 'boolean',
        'is_public' => 'boolean',
        'file_size' => 'integer',
        'sort_order' => 'integer'
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    public function scopeOptional(Builder $query): Builder
    {
        return $query->where('is_required', false);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('content_type', $type);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        $today = now()->toDateString();
        return $query->where(function ($q) use ($today) {
            $q->where(function ($q2) use ($today) {
                $q2->whereNull('available_from')
                   ->orWhere('available_from', '<=', $today);
            })->where(function ($q2) use ($today) {
                $q2->whereNull('available_until')
                   ->orWhere('available_until', '>=', $today);
            });
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    // Methods
    public function isFile(): bool
    {
        return in_array($this->content_type, [
            'document', 'image', 'audio', 'presentation',
            'worksheet', 'meeting_recording'
        ]);
    }

    public function isUrl(): bool
    {
        return in_array($this->content_type, [
            'video', 'link', 'live_stream', 'external_resource'
        ]);
    }

    public function isRequired(): bool
    {
        return $this->is_required;
    }

    public function isPublic(): bool
    {
        return $this->is_public;
    }

    public function isDownloadable(): bool
    {
        return $this->is_downloadable && $this->isFile();
    }

    public function isAvailable(): bool
    {
        $today = now()->toDateString();

        $availableFrom = $this->available_from ? $this->available_from->lte(now()) : true;
        $availableUntil = $this->available_until ? $this->available_until->gte(now()) : true;

        return $availableFrom && $availableUntil;
    }

    public function getFileUrl(): ?string
    {
        if (!$this->file_path) return null;
        return Storage::url($this->file_path);
    }

    public function getDownloadUrl(): ?string
    {
        if (!$this->isDownloadable()) return null;
        return route('api.v1.lesson-contents.download', $this->id);
    }

    public function getFileSizeFormatted(): ?string
    {
        if (!$this->file_size) return null;

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    public function getContentTypeLabelAttribute(): string
    {
        $labels = [
            'document' => 'Documento',
            'video' => 'Vídeo',
            'audio' => 'Áudio',
            'link' => 'Link',
            'image' => 'Imagem',
            'presentation' => 'Apresentação',
            'worksheet' => 'Atividade',
            'quiz' => 'Questionário',
            'assignment' => 'Tarefa',
            'meeting_recording' => 'Gravação da aula',
            'live_stream' => 'Transmissão ao vivo',
            'external_resource' => 'Recurso externo'
        ];
        return $labels[$this->content_type] ?? ucfirst($this->content_type);
    }

    public function getIconClass(): string
    {
        $icons = [
            'document' => 'fas fa-file-alt',
            'video' => 'fas fa-video',
            'audio' => 'fas fa-volume-up',
            'link' => 'fas fa-external-link-alt',
            'image' => 'fas fa-image',
            'presentation' => 'fas fa-file-powerpoint',
            'worksheet' => 'fas fa-tasks',
            'quiz' => 'fas fa-question-circle',
            'assignment' => 'fas fa-clipboard-list',
            'meeting_recording' => 'fas fa-microphone',
            'live_stream' => 'fas fa-broadcast-tower',
            'external_resource' => 'fas fa-globe'
        ];
        return $icons[$this->content_type] ?? 'fas fa-file';
    }
}
EOF

# 2.4 Lesson Attendance Model
cat > app/Models/V1/Schedule/LessonAttendance.php << 'EOF'
<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

class LessonAttendance extends BaseModel
{
    protected $fillable = [
        'school_id', 'lesson_id', 'student_id',
        'status', 'arrival_time', 'departure_time', 'minutes_late', 'minutes_present',
        'marked_by_method', 'notes', 'notified_parent', 'parent_notified_at',
        'check_in_latitude', 'check_in_longitude', 'device_info', 'ip_address',
        'requires_approval', 'approval_status', 'approved_by', 'approved_at', 'approval_notes',
        'marked_by', 'updated_by'
    ];

    protected $casts = [
        'arrival_time' => 'datetime:H:i:s',
        'departure_time' => 'datetime:H:i:s',
        'parent_notified_at' => 'datetime',
        'approved_at' => 'datetime',
        'notified_parent' => 'boolean',
        'requires_approval' => 'boolean',
        'check_in_latitude' => 'decimal:8',
        'check_in_longitude' => 'decimal:8',
        'minutes_late' => 'integer',
        'minutes_present' => 'integer'
    ];

    protected static function booted()
    {
        static::creating(function ($attendance) {
            // Calculate minutes late if arrival time is set
            if ($attendance->arrival_time && $attendance->lesson) {
                $lessonStart = Carbon::parse($attendance->lesson->start_time);
                $arrival = Carbon::parse($attendance->arrival_time);

                if ($arrival->gt($lessonStart)) {
                    $attendance->minutes_late = $arrival->diffInMinutes($lessonStart);
                    if ($attendance->minutes_late > 15 && $attendance->status === 'present') {
                        $attendance->status = 'late';
                    }
                }
            }
        });
    }

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopePresent(Builder $query): Builder
    {
        return $query->whereIn('status', ['present', 'late', 'online_present']);
    }

    public function scopeAbsent(Builder $query): Builder
    {
        return $query->whereIn('status', ['absent', 'excused']);
    }

    public function scopeLate(Builder $query): Builder
    {
        return $query->where('status', 'late');
    }

    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeByStudent(Builder $query, int $studentId): Builder
    {
        return $query->where('student_id', $studentId);
    }

    public function scopeByLesson(Builder $query, int $lessonId): Builder
    {
        return $query->where('lesson_id', $lessonId);
    }

    public function scopeRequiringApproval(Builder $query): Builder
    {
        return $query->where('requires_approval', true)
                     ->where('approval_status', 'pending');
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('approval_status', 'approved');
    }

    // Methods
    public function isPresent(): bool
    {
        return in_array($this->status, ['present', 'late', 'online_present', 'left_early', 'partial']);
    }

    public function isAbsent(): bool
    {
        return in_array($this->status, ['absent', 'excused']);
    }

    public function isLate(): bool
    {
        return $this->status === 'late' || $this->minutes_late > 0;
    }

    public function isExcused(): bool
    {
        return $this->status === 'excused';
    }

    public function requiresApproval(): bool
    {
        return $this->requires_approval && $this->approval_status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->approval_status === 'approved';
    }

    public function calculateMinutesPresent(): int
    {
        if (!$this->arrival_time || !$this->departure_time) {
            return 0;
        }

        $arrival = Carbon::parse($this->arrival_time);
        $departure = Carbon::parse($this->departure_time);

        return $departure->diffInMinutes($arrival);
    }

    public function markAsPresent(string $method = 'teacher_manual', array $additionalData = []): bool
    {
        $data = array_merge([
            'status' => 'present',
            'marked_by_method' => $method,
            'arrival_time' => now(),
        ], $additionalData);

        return $this->update($data);
    }

    public function markAsAbsent(string $reason = null): bool
    {
        return $this->update([
            'status' => 'absent',
            'notes' => $reason
        ]);
    }

    public function markAsLate(int $minutesLate, string $method = 'teacher_manual'): bool
    {
        return $this->update([
            'status' => 'late',
            'minutes_late' => $minutesLate,
            'arrival_time' => now(),
            'marked_by_method' => $method
        ]);
    }

    public function excuse(string $reason): bool
    {
        return $this->update([
            'status' => 'excused',
            'notes' => $reason
        ]);
    }

    public function approve(int $userId, string $notes = null): bool
    {
        return $this->update([
            'approval_status' => 'approved',
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes
        ]);
    }

    public function reject(int $userId, string $notes = null): bool
    {
        return $this->update([
            'approval_status' => 'rejected',
            'approved_by' => $userId,
            'approved_at' => now(),
            'approval_notes' => $notes
        ]);
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'present' => 'Presente',
            'absent' => 'Ausente',
            'late' => 'Atrasado',
            'excused' => 'Justificado',
            'left_early' => 'Saída antecipada',
            'partial' => 'Presença parcial',
            'online_present' => 'Presente (online)'
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }

    public function getMethodLabelAttribute(): string
    {
        $labels = [
            'teacher_manual' => 'Manual (Professor)',
            'qr_code' => 'QR Code',
            'student_self_checkin' => 'Auto check-in',
            'automatic_online' => 'Automático (online)',
            'biometric' => 'Biometria',
            'rfid' => 'RFID'
        ];
        return $labels[$this->marked_by_method] ?? ucfirst($this->marked_by_method);
    }
}
EOF

# 2.5 Schedule Conflict Model
cat > app/Models/V1/Schedule/ScheduleConflict.php << 'EOF'
<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class ScheduleConflict extends BaseModel
{
    protected $fillable = [
        'school_id', 'conflict_type', 'conflict_description',
        'conflicting_schedule_ids', 'affected_entities',
        'conflict_date', 'conflict_start_time', 'conflict_end_time', 'severity',
        'status', 'resolution_notes', 'resolved_by', 'resolved_at',
        'detection_method', 'detected_by'
    ];

    protected $casts = [
        'conflicting_schedule_ids' => 'array',
        'affected_entities' => 'array',
        'conflict_date' => 'date',
        'conflict_start_time' => 'datetime:H:i:s',
        'conflict_end_time' => 'datetime:H:i:s',
        'resolved_at' => 'datetime'
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function detectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'detected_by');
    }

    // Scopes
    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', ['detected', 'acknowledged']);
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->where('status', 'resolved');
    }

    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', 'critical');
    }

    public function scopeHigh(Builder $query): Builder
    {
        return $query->where('severity', 'high');
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('conflict_type', $type);
    }

    // Methods
    public function isResolved(): bool
    {
        return $this->status === 'resolved';
    }

    public function isCritical(): bool
    {
        return $this->severity === 'critical';
    }

    public function resolve(int $userId, string $notes): bool
    {
        return $this->update([
            'status' => 'resolved',
            'resolved_by' => $userId,
            'resolved_at' => now(),
            'resolution_notes' => $notes
        ]);
    }

    public function acknowledge(): bool
    {
        return $this->update(['status' => 'acknowledged']);
    }

    public function ignore(): bool
    {
        return $this->update(['status' => 'ignored']);
    }

    public function getConflictingSchedules()
    {
        if (empty($this->conflicting_schedule_ids)) {
            return collect();
        }

        return Schedule::whereIn('id', $this->conflicting_schedule_ids)
                      ->with(['teacher', 'subject', 'class'])
                      ->get();
    }

    public function getSeverityColorAttribute(): string
    {
        $colors = [
            'low' => 'green',
            'medium' => 'yellow',
            'high' => 'orange',
            'critical' => 'red'
        ];
        return $colors[$this->severity] ?? 'gray';
    }

    public function getTypeLabelAttribute(): string
    {
        $labels = [
            'teacher_double_booking' => 'Conflito de Horário do Professor',
            'classroom_double_booking' => 'Conflito de Sala de Aula',
            'student_schedule_overlap' => 'Sobreposição de Horário do Aluno',
            'resource_conflict' => 'Conflito de Recurso',
            'time_constraint_violation' => 'Violação de Restrição de Tempo',
            'capacity_exceeded' => 'Capacidade Excedida'
        ];
        return $labels[$this->conflict_type] ?? ucfirst($this->conflict_type);
    }

    public function getSeverityLabelAttribute(): string
    {
        $labels = [
            'low' => 'Baixa',
            'medium' => 'Média',
            'high' => 'Alta',
            'critical' => 'Crítica'
        ];
        return $labels[$this->severity] ?? ucfirst($this->severity);
    }

    public function getStatusLabelAttribute(): string
    {
        $labels = [
            'detected' => 'Detectado',
            'acknowledged' => 'Reconhecido',
            'resolved' => 'Resolvido',
            'ignored' => 'Ignorado'
        ];
        return $labels[$this->status] ?? ucfirst($this->status);
    }
}
EOF

# =============================================================================
# 3. REPOSITORIES
# =============================================================================

echo "🗃️ Creating repositories..."

mkdir -p app/Repositories/V1/Schedule

# 3.1 Base Schedule Repository
cat > app/Repositories/V1/Schedule/BaseScheduleRepository.php << 'EOF'
<?php

namespace App\Repositories\V1\Schedule;

use App\Services\SchoolContextService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

abstract class BaseScheduleRepository
{
    protected Model $model;
    protected SchoolContextService $schoolContextService;

    public function __construct(SchoolContextService $schoolContextService)
    {
        $this->schoolContextService = $schoolContextService;
        $this->model = app($this->getModelClass());
    }

    abstract protected function getModelClass(): string;

    protected function getCurrentSchoolId(): int
    {
        return $this->schoolContextService->getCurrentSchool()->id;
    }

    protected function schoolScoped(): Builder
    {
        return $this->model->where('school_id', $this->getCurrentSchoolId());
    }

    public function find(int $id): ?Model
    {
        return $this->schoolScoped()->find($id);
    }

    public function findOrFail(int $id): Model
    {
        return $this->schoolScoped()->findOrFail($id);
    }

    public function create(array $data): Model
    {
        $data['school_id'] = $this->getCurrentSchoolId();
        return $this->model->create($data);
    }

    public function update(Model $model, array $data): Model
    {
        $model->update($data);
        return $model->fresh();
    }

    public function delete(Model $model): bool
    {
        return $model->delete();
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        if (isset($filters['search'])) {
            $query = $this->applySearch($query, $filters['search']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['sort_by'])) {
            $direction = $filters['sort_direction'] ?? 'asc';
            $query->orderBy($filters['sort_by'], $direction);
        }

        return $query;
    }

    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query;
    }

    public function getWithFilters(array $filters = []): LengthAwarePaginator
    {
        $query = $this->schoolScoped();
        $query = $this->applyFilters($query, $filters);

        $perPage = $filters['per_page'] ?? 15;
        return $query->paginate($perPage);
    }
}
EOF

# 3.2 Schedule Repository
cat > app/Repositories/V1/Schedule/ScheduleRepository.php << 'EOF'
<?php

namespace App\Repositories\V1\Schedule;

use App\Models\V1\Schedule\Schedule;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ScheduleRepository extends BaseScheduleRepository
{
    protected function getModelClass(): string
    {
        return Schedule::class;
    }

    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
              ->orWhere('classroom', 'like', "%{$search}%")
              ->orWhereHas('subject', function ($sq) use ($search) {
                  $sq->where('name', 'like', "%{$search}%");
              })
              ->orWhereHas('teacher', function ($tq) use ($search) {
                  $tq->where('first_name', 'like', "%{$search}%")
                     ->orWhere('last_name', 'like', "%{$search}%");
              })
              ->orWhereHas('class', function ($cq) use ($search) {
                  $cq->where('name', 'like', "%{$search}%");
              });
        });
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query = parent::applyFilters($query, $filters);

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['period'])) {
            $query->where('period', $filters['period']);
        }

        if (isset($filters['day_of_week'])) {
            $query->where('day_of_week', $filters['day_of_week']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        if (isset($filters['is_online'])) {
            $query->where('is_online', $filters['is_online']);
        }

        return $query;
    }

    public function getByTeacher(int $teacherId): Collection
    {
        return $this->schoolScoped()
            ->where('teacher_id', $teacherId)
            ->where('status', 'active')
            ->with(['subject', 'class', 'academicYear'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function getByClass(int $classId): Collection
    {
        return $this->schoolScoped()
            ->where('class_id', $classId)
            ->where('status', 'active')
            ->with(['subject', 'teacher', 'academicYear'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function getWeeklySchedule(array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->where('status', 'active')
            ->with(['subject', 'teacher', 'class']);

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['academic_year_id'])) {
            $query->where('academic_year_id', $filters['academic_year_id']);
        }

        return $query->orderBy('day_of_week')
                    ->orderBy('start_time')
                    ->get();
    }

    public function findConflicts(int $teacherId, string $dayOfWeek, string $startTime, string $endTime, ?int $excludeId = null): Collection
    {
        $query = $this->schoolScoped()
            ->conflictsWith($teacherId, $dayOfWeek, $startTime, $endTime);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->with(['subject', 'class'])->get();
    }

    public function getByTimeSlot(string $dayOfWeek, string $startTime, string $endTime): Collection
    {
        return $this->schoolScoped()
            ->byDay($dayOfWeek)
            ->byTimeRange($startTime, $endTime)
            ->where('status', 'active')
            ->with(['teacher', 'subject', 'class'])
            ->get();
    }

    public function getClassroomSchedule(string $classroom): Collection
    {
        return $this->schoolScoped()
            ->where('classroom', $classroom)
            ->where('status', 'active')
            ->with(['teacher', 'subject', 'class'])
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get();
    }

    public function getDashboardStats(): array
    {
        $query = $this->schoolScoped();

        return [
            'total' => $query->count(),
            'active' => $query->where('status', 'active')->count(),
            'suspended' => $query->where('status', 'suspended')->count(),
            'by_period' => $query->where('status', 'active')
                ->groupBy('period')
                ->selectRaw('period, count(*) as count')
                ->pluck('count', 'period')
                ->toArray(),
            'online_classes' => $query->where('is_online', true)->count(),
            'unique_teachers' => $query->distinct('teacher_id')->count(),
            'unique_classrooms' => $query->whereNotNull('classroom')->distinct('classroom')->count()
        ];
    }
}
EOF

# 3.3 Lesson Repository
cat > app/Repositories/V1/Schedule/LessonRepository.php << 'EOF'
<?php

namespace App\Repositories\V1\Schedule;

use App\Models\V1\Schedule\Lesson;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Carbon\Carbon;

class LessonRepository extends BaseScheduleRepository
{
    protected function getModelClass(): string
    {
        return Lesson::class;
    }

    protected function applySearch(Builder $query, string $search): Builder
    {
        return $query->where(function ($q) use ($search) {
            $q->where('title', 'like', "%{$search}%")
              ->orWhere('description', 'like', "%{$search}%")
              ->orWhere('content_summary', 'like', "%{$search}%")
              ->orWhereHas('subject', function ($sq) use ($search) {
                  $sq->where('name', 'like', "%{$search}%");
              })
              ->orWhereHas('teacher', function ($tq) use ($search) {
                  $tq->where('first_name', 'like', "%{$search}%")
                     ->orWhere('last_name', 'like', "%{$search}%");
              });
        });
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        $query = parent::applyFilters($query, $filters);

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        if (isset($filters['subject_id'])) {
            $query->where('subject_id', $filters['subject_id']);
        }

        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (isset($filters['date_from'])) {
            $query->where('lesson_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('lesson_date', '<=', $filters['date_to']);
        }

        if (isset($filters['is_online'])) {
            $query->where('is_online', $filters['is_online']);
        }

        return $query;
    }

    public function getByTeacher(int $teacherId, array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->where('teacher_id', $teacherId)
            ->with(['subject', 'class', 'schedule']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('lesson_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('lesson_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('lesson_date', 'desc')
                    ->orderBy('start_time')
                    ->get();
    }

    public function getByClass(int $classId, array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->where('class_id', $classId)
            ->with(['subject', 'teacher', 'schedule']);

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['date_from'])) {
            $query->where('lesson_date', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('lesson_date', '<=', $filters['date_to']);
        }

        return $query->orderBy('lesson_date', 'desc')
                    ->orderBy('start_time')
                    ->get();
    }

    public function getUpcoming(int $limit = 10, array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->upcoming()
            ->with(['subject', 'teacher', 'class']);

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        return $query->limit($limit)->get();
    }

    public function getToday(array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->today()
            ->with(['subject', 'teacher', 'class']);

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        return $query->orderBy('start_time')->get();
    }

    public function getThisWeek(array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->thisWeek()
            ->with(['subject', 'teacher', 'class']);

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        return $query->orderBy('lesson_date')
                    ->orderBy('start_time')
                    ->get();
    }

    public function getByDateRange(string $startDate, string $endDate, array $filters = []): Collection
    {
        $query = $this->schoolScoped()
            ->byDateRange($startDate, $endDate)
            ->with(['subject', 'teacher', 'class']);

        if (isset($filters['teacher_id'])) {
            $query->where('teacher_id', $filters['teacher_id']);
        }

        if (isset($filters['class_id'])) {
            $query->where('class_id', $filters['class_id']);
        }

        return $query->orderBy('lesson_date')
                    ->orderBy('start_time')
                    ->get();
    }

    public function getDashboardStats(): array
    {
        $query = $this->schoolScoped();
        $today = now()->toDateString();

        return [
            'total' => $query->count(),
            'today' => $query->where('lesson_date', $today)->count(),
            'this_week' => $query->whereBetween('lesson_date', [
                now()->startOfWeek()->toDateString(),
                now()->endOfWeek()->toDateString()
            ])->count(),
            'completed_today' => $query->where('lesson_date', $today)
                ->where('status', 'completed')->count(),
            'upcoming_today' => $query->where('lesson_date', $today)
                ->where('status', 'scheduled')->count(),
            'by_status' => $query->groupBy('status')
                ->selectRaw('status, count(*) as count')
                ->pluck('count', 'status')
                ->toArray(),
            'by_type' => $query->groupBy('type')
                ->selectRaw('type, count(*) as count')
                ->pluck('count', 'type')
                ->toArray(),
            'online_lessons' => $query->where('is_online', true)->count(),
            'average_attendance' => $query->whereNotNull('attendance_rate')
                ->avg('attendance_rate') ?? 0
        ];
    }

    public function getAttendanceStats(): array
    {
        $lessons = $this->schoolScoped()
            ->where('status', 'completed')
            ->whereNotNull('attendance_rate')
            ->get(['attendance_rate', 'expected_students', 'present_students']);

        if ($lessons->isEmpty()) {
            return [
                'average_attendance_rate' => 0,
                'total_expected' => 0,
                'total_present' => 0,
                'lessons_count' => 0
            ];
        }

        return [
            'average_attendance_rate' => round($lessons->avg('attendance_rate'), 2),
            'total_expected' => $lessons->sum('expected_students'),
            'total_present' => $lessons->sum('present_students'),
            'lessons_count' => $lessons->count()
        ];
    }
}
EOF

echo "✅ Schedule & Lessons Management Module created successfully!"
echo ""
echo "📋 Created components:"
echo "   📊 Database migrations (5 tables)"
echo "   🎯 Models (5 models with relationships)"
echo "   🗃️ Repositories (3 repositories)"
echo ""
echo "🚀 Next steps:"
echo "   1. Run: php artisan migrate"
echo "   2. Create Services, Controllers, and Resources"
echo "   3. Set up Routes and Policies"
echo "   4. Configure Notifications and Workflows"
echo ""
echo "📁 Files created:"
echo "   - database/migrations/ (5 migration files)"
echo "   - app/Models/V1/Schedule/ (Schedule, Lesson, LessonContent, LessonAttendance, ScheduleConflict)"
echo "   - app/Repositories/V1/Schedule/ (BaseScheduleRepository, ScheduleRepository, LessonRepository)"
