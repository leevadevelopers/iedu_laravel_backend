#!/bin/bash

# iEDU Academic Management - Controllers Generation
# Creates all Laravel controllers for the Academic Management module

echo "🎮 Creating iEDU Academic Management Controllers..."

# Create Controllers directory if not exists
mkdir -p app/Http/Controllers/API/V1/Academic

# Teacher Controller
cat > app/Http/Controllers/API/V1/Academic/TeacherController.php << 'EOF'
<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\Teacher;
use App\Http\Requests\Academic\StoreTeacherRequest;
use App\Http\Requests\Academic\UpdateTeacherRequest;
use App\Http\Resources\Academic\TeacherResource;
use App\Services\V1\Academic\TeacherService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TeacherController extends Controller
{
    protected TeacherService $teacherService;

    public function __construct(TeacherService $teacherService)
    {
        $this->teacherService = $teacherService;
    }

    /**
     * Display a listing of teachers
     */
    public function index(Request $request): JsonResponse
    {
        $teachers = $this->teacherService->getTeachers($request->all());

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers),
            'meta' => [
                'total' => $teachers->total(),
                'per_page' => $teachers->perPage(),
                'current_page' => $teachers->currentPage(),
                'last_page' => $teachers->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created teacher
     */
    public function store(StoreTeacherRequest $request): JsonResponse
    {
        try {
            $teacher = $this->teacherService->createTeacher($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher created successfully',
                'data' => new TeacherResource($teacher)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create teacher',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified teacher
     */
    public function show(Teacher $teacher): JsonResponse
    {
        $this->authorize('view', $teacher);

        return response()->json([
            'status' => 'success',
            'data' => new TeacherResource($teacher->load(['user', 'classes.subject', 'classes.academicYear']))
        ]);
    }

    /**
     * Update the specified teacher
     */
    public function update(UpdateTeacherRequest $request, Teacher $teacher): JsonResponse
    {
        $this->authorize('update', $teacher);

        try {
            $updatedTeacher = $this->teacherService->updateTeacher($teacher, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher updated successfully',
                'data' => new TeacherResource($updatedTeacher)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update teacher',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified teacher
     */
    public function destroy(Teacher $teacher): JsonResponse
    {
        $this->authorize('delete', $teacher);

        try {
            $this->teacherService->deleteTeacher($teacher);

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher terminated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to terminate teacher',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get teachers by department
     */
    public function byDepartment(Request $request): JsonResponse
    {
        $request->validate([
            'department' => 'required|string|max:100'
        ]);

        $teachers = $this->teacherService->getTeachersByDepartment($request->department);

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
    }

    /**
     * Get teachers by employment type
     */
    public function byEmploymentType(Request $request): JsonResponse
    {
        $request->validate([
            'employment_type' => 'required|in:full_time,part_time,substitute,contract,volunteer'
        ]);

        $teachers = $this->teacherService->getTeachersByEmploymentType($request->employment_type);

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
    }

    /**
     * Get teachers by specialization
     */
    public function bySpecialization(Request $request): JsonResponse
    {
        $request->validate([
            'specialization' => 'required|string'
        ]);

        $teachers = $this->teacherService->getTeachersBySpecialization($request->specialization);

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
    }

    /**
     * Get teachers by grade level
     */
    public function byGradeLevel(Request $request): JsonResponse
    {
        $request->validate([
            'grade_level' => 'required|string'
        ]);

        $teachers = $this->teacherService->getTeachersByGradeLevel($request->grade_level);

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
    }

    /**
     * Search teachers
     */
    public function search(Request $request): JsonResponse
    {
        $request->validate([
            'search' => 'required|string|min:2'
        ]);

        $teachers = $this->teacherService->searchTeachers($request->search);

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
    }

    /**
     * Get teacher workload
     */
    public function workload(Teacher $teacher): JsonResponse
    {
        $this->authorize('view', $teacher);

        $workload = $this->teacherService->getTeacherWorkload($teacher);

        return response()->json([
            'status' => 'success',
            'data' => $workload
        ]);
    }

    /**
     * Get teacher's classes
     */
    public function classes(Teacher $teacher, Request $request): JsonResponse
    {
        $this->authorize('view', $teacher);

        $classes = $this->teacherService->getTeacherClasses($teacher, $request->all());

        return response()->json([
            'status' => 'success',
            'data' => $classes
        ]);
    }

    /**
     * Get teacher statistics
     */
    public function statistics(Teacher $teacher): JsonResponse
    {
        $this->authorize('view', $teacher);

        $statistics = $this->teacherService->getTeacherStatistics($teacher);

        return response()->json([
            'status' => 'success',
            'data' => $statistics
        ]);
    }

    /**
     * Update teacher schedule
     */
    public function updateSchedule(Teacher $teacher, Request $request): JsonResponse
    {
        $this->authorize('update', $teacher);

        $request->validate([
            'schedule' => 'required|array',
            'schedule.*' => 'array',
            'schedule.*.available_times' => 'array',
            'schedule.*.available_times.*' => 'string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'
        ]);

        try {
            $updatedTeacher = $this->teacherService->updateTeacherSchedule($teacher, $request->schedule);

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher schedule updated successfully',
                'data' => new TeacherResource($updatedTeacher)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update teacher schedule',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Check teacher availability
     */
    public function checkAvailability(Teacher $teacher, Request $request): JsonResponse
    {
        $this->authorize('view', $teacher);

        $request->validate([
            'day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'time' => 'required|string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'
        ]);

        $isAvailable = $this->teacherService->checkTeacherAvailability(
            $teacher,
            $request->day,
            $request->time
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'teacher_id' => $teacher->id,
                'day' => $request->day,
                'time' => $request->time,
                'available' => $isAvailable
            ]
        ]);
    }

    /**
     * Get available teachers at specific time
     */
    public function availableAt(Request $request): JsonResponse
    {
        $request->validate([
            'day' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'time' => 'required|string|regex:/^([0-1]?[0-9]|2[0-3]):[0-5][0-9]$/'
        ]);

        $teachers = $this->teacherService->getAvailableTeachers($request->day, $request->time);

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
    }

    /**
     * Assign teacher to class
     */
    public function assignToClass(Teacher $teacher, Request $request): JsonResponse
    {
        $this->authorize('update', $teacher);

        $request->validate([
            'class_id' => 'required|exists:classes,id'
        ]);

        try {
            $this->teacherService->assignTeacherToClass($teacher, $request->class_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Teacher assigned to class successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to assign teacher to class',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get teacher performance metrics
     */
    public function performanceMetrics(Teacher $teacher, Request $request): JsonResponse
    {
        $this->authorize('view', $teacher);

        $request->validate([
            'academic_term_id' => 'required|exists:academic_terms,id'
        ]);

        $metrics = $this->teacherService->getTeacherPerformanceMetrics(
            $teacher,
            $request->academic_term_id
        );

        return response()->json([
            'status' => 'success',
            'data' => $metrics
        ]);
    }

    /**
     * Get teacher dashboard data
     */
    public function dashboard(Teacher $teacher): JsonResponse
    {
        $this->authorize('view', $teacher);

        $workload = $this->teacherService->getTeacherWorkload($teacher);
        $classes = $this->teacherService->getTeacherClasses($teacher);
        $statistics = $this->teacherService->getTeacherStatistics($teacher);

        return response()->json([
            'status' => 'success',
            'data' => [
                'teacher' => new TeacherResource($teacher),
                'workload' => $workload,
                'classes' => $classes,
                'statistics' => $statistics
            ]
        ]);
    }

    /**
     * Get teachers for class assignment
     */
    public function forClassAssignment(Request $request): JsonResponse
    {
        $request->validate([
            'subject_id' => 'required|exists:subjects,id',
            'grade_level' => 'required|string'
        ]);

        $teachers = $this->teacherService->getTeachersBySpecialization($request->subject_id)
            ->merge($this->teacherService->getTeachersByGradeLevel($request->grade_level))
            ->unique('id');

        return response()->json([
            'status' => 'success',
            'data' => TeacherResource::collection($teachers)
        ]);
    }
}
EOF

# Subject Controller
cat > app/Http/Controllers/API/V1/Academic/SubjectController.php << 'EOF'
<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\Subject;
use App\Http\Requests\Academic\StoreSubjectRequest;
use App\Http\Requests\Academic\UpdateSubjectRequest;
use App\Http\Resources\Academic\SubjectResource;
use App\Services\Academic\SubjectService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    protected SubjectService $subjectService;

    public function __construct(SubjectService $subjectService)
    {
        $this->subjectService = $subjectService;
    }

    /**
     * Display a listing of subjects
     */
    public function index(Request $request): JsonResponse
    {
        $subjects = $this->subjectService->getSubjects($request->all());

        return response()->json([
            'status' => 'success',
            'data' => SubjectResource::collection($subjects),
            'meta' => [
                'total' => $subjects->total(),
                'per_page' => $subjects->perPage(),
                'current_page' => $subjects->currentPage(),
                'last_page' => $subjects->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created subject
     */
    public function store(StoreSubjectRequest $request): JsonResponse
    {
        try {
            $subject = $this->subjectService->createSubject($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Subject created successfully',
                'data' => new SubjectResource($subject)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create subject',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified subject
     */
    public function show(Subject $subject): JsonResponse
    {
        $this->authorize('view', $subject);

        return response()->json([
            'status' => 'success',
            'data' => new SubjectResource($subject->load(['classes', 'school']))
        ]);
    }

    /**
     * Update the specified subject
     */
    public function update(UpdateSubjectRequest $request, Subject $subject): JsonResponse
    {
        $this->authorize('update', $subject);

        try {
            $updatedSubject = $this->subjectService->updateSubject($subject, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Subject updated successfully',
                'data' => new SubjectResource($updatedSubject)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update subject',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified subject
     */
    public function destroy(Subject $subject): JsonResponse
    {
        $this->authorize('delete', $subject);

        try {
            $this->subjectService->deleteSubject($subject);

            return response()->json([
                'status' => 'success',
                'message' => 'Subject archived successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to archive subject',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get subjects by grade level
     */
    public function byGradeLevel(string $gradeLevel): JsonResponse
    {
        $subjects = $this->subjectService->getSubjectsByGradeLevel($gradeLevel);

        return response()->json([
            'status' => 'success',
            'data' => SubjectResource::collection($subjects)
        ]);
    }

    /**
     * Get core subjects
     */
    public function core(): JsonResponse
    {
        $subjects = $this->subjectService->getCoreSubjects();

        return response()->json([
            'status' => 'success',
            'data' => SubjectResource::collection($subjects)
        ]);
    }

    /**
     * Get elective subjects
     */
    public function electives(): JsonResponse
    {
        $subjects = $this->subjectService->getElectiveSubjects();

        return response()->json([
            'status' => 'success',
            'data' => SubjectResource::collection($subjects)
        ]);
    }
}
EOF

# Academic Class Controller
cat > app/Http/Controllers/API/V1/Academic/AcademicClassController.php << 'EOF'
<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\AcademicClass;
use App\Http\Requests\Academic\StoreAcademicClassRequest;
use App\Http\Requests\Academic\UpdateAcademicClassRequest;
use App\Http\Resources\Academic\AcademicClassResource;
use App\Services\Academic\AcademicClassService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class AcademicClassController extends Controller
{
    protected AcademicClassService $classService;

    public function __construct(AcademicClassService $classService)
    {
        $this->classService = $classService;
    }

    /**
     * Display a listing of classes
     */
    public function index(Request $request): JsonResponse
    {
        $classes = $this->classService->getClasses($request->all());

        return response()->json([
            'status' => 'success',
            'data' => AcademicClassResource::collection($classes),
            'meta' => [
                'total' => $classes->total(),
                'per_page' => $classes->perPage(),
                'current_page' => $classes->currentPage(),
                'last_page' => $classes->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created class
     */
    public function store(StoreAcademicClassRequest $request): JsonResponse
    {
        try {
            $class = $this->classService->createClass($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Class created successfully',
                'data' => new AcademicClassResource($class)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create class',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified class
     */
    public function show(AcademicClass $class): JsonResponse
    {
        $this->authorize('view', $class);

        return response()->json([
            'status' => 'success',
            'data' => new AcademicClassResource($class->load([
                'subject', 'primaryTeacher', 'students', 'academicYear', 'academicTerm'
            ]))
        ]);
    }

    /**
     * Update the specified class
     */
    public function update(UpdateAcademicClassRequest $request, AcademicClass $class): JsonResponse
    {
        $this->authorize('update', $class);

        try {
            $updatedClass = $this->classService->updateClass($class, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Class updated successfully',
                'data' => new AcademicClassResource($updatedClass)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update class',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified class
     */
    public function destroy(AcademicClass $class): JsonResponse
    {
        $this->authorize('delete', $class);

        try {
            $this->classService->deleteClass($class);

            return response()->json([
                'status' => 'success',
                'message' => 'Class deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete class',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Enroll student in class
     */
    public function enrollStudent(AcademicClass $class, Request $request): JsonResponse
    {
        $this->authorize('update', $class);

        $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        try {
            $enrollment = $this->classService->enrollStudent($class, $request->student_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Student enrolled successfully',
                'data' => $enrollment
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to enroll student',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove student from class
     */
    public function removeStudent(AcademicClass $class, Request $request): JsonResponse
    {
        $this->authorize('update', $class);

        $request->validate([
            'student_id' => 'required|exists:students,id',
        ]);

        try {
            $this->classService->removeStudent($class, $request->student_id);

            return response()->json([
                'status' => 'success',
                'message' => 'Student removed successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to remove student',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get class roster
     */
    public function roster(AcademicClass $class): JsonResponse
    {
        $this->authorize('view', $class);

        $roster = $this->classService->getClassRoster($class);

        return response()->json([
            'status' => 'success',
            'data' => $roster
        ]);
    }

    /**
     * Get teacher's classes
     */
    public function teacherClasses(Request $request): JsonResponse
    {
        $teacherId = $request->get('teacher_id', auth()->id());
        $classes = $this->classService->getTeacherClasses($teacherId, $request->all());

        return response()->json([
            'status' => 'success',
            'data' => AcademicClassResource::collection($classes)
        ]);
    }
}
EOF

# Grading System Controller
cat > app/Http/Controllers/API/V1/Academic/GradingSystemController.php << 'EOF'
<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\GradingSystem;
use App\Http\Requests\Academic\StoreGradingSystemRequest;
use App\Http\Requests\Academic\UpdateGradingSystemRequest;
use App\Http\Resources\Academic\GradingSystemResource;
use App\Services\Academic\GradingSystemService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GradingSystemController extends Controller
{
    protected GradingSystemService $gradingSystemService;

    public function __construct(GradingSystemService $gradingSystemService)
    {
        $this->gradingSystemService = $gradingSystemService;
    }

    /**
     * Display a listing of grading systems
     */
    public function index(Request $request): JsonResponse
    {
        $gradingSystems = $this->gradingSystemService->getGradingSystems($request->all());

        return response()->json([
            'status' => 'success',
            'data' => GradingSystemResource::collection($gradingSystems)
        ]);
    }

    /**
     * Store a newly created grading system
     */
    public function store(StoreGradingSystemRequest $request): JsonResponse
    {
        try {
            $gradingSystem = $this->gradingSystemService->createGradingSystem($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Grading system created successfully',
                'data' => new GradingSystemResource($gradingSystem)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create grading system',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified grading system
     */
    public function show(GradingSystem $gradingSystem): JsonResponse
    {
        $this->authorize('view', $gradingSystem);

        return response()->json([
            'status' => 'success',
            'data' => new GradingSystemResource($gradingSystem->load('gradeScales.gradeLevels'))
        ]);
    }

    /**
     * Update the specified grading system
     */
    public function update(UpdateGradingSystemRequest $request, GradingSystem $gradingSystem): JsonResponse
    {
        $this->authorize('update', $gradingSystem);

        try {
            $updatedGradingSystem = $this->gradingSystemService->updateGradingSystem($gradingSystem, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Grading system updated successfully',
                'data' => new GradingSystemResource($updatedGradingSystem)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update grading system',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified grading system
     */
    public function destroy(GradingSystem $gradingSystem): JsonResponse
    {
        $this->authorize('delete', $gradingSystem);

        try {
            $this->gradingSystemService->deleteGradingSystem($gradingSystem);

            return response()->json([
                'status' => 'success',
                'message' => 'Grading system deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete grading system',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get primary grading system
     */
    public function primary(): JsonResponse
    {
        $primarySystem = $this->gradingSystemService->getPrimaryGradingSystem();

        if (!$primarySystem) {
            return response()->json([
                'status' => 'error',
                'message' => 'No primary grading system found'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => new GradingSystemResource($primarySystem->load('gradeScales.gradeLevels'))
        ]);
    }

    /**
     * Set grading system as primary
     */
    public function setPrimary(GradingSystem $gradingSystem): JsonResponse
    {
        $this->authorize('update', $gradingSystem);

        try {
            $this->gradingSystemService->setPrimaryGradingSystem($gradingSystem);

            return response()->json([
                'status' => 'success',
                'message' => 'Grading system set as primary successfully',
                'data' => new GradingSystemResource($gradingSystem)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to set primary grading system',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
EOF

# Grade Entry Controller
cat > app/Http/Controllers/API/V1/Academic/GradeEntryController.php << 'EOF'
<?php

namespace App\Http\Controllers\API\V1\Academic;

use App\Http\Controllers\Controller;
use App\Models\V1\Academic\GradeEntry;
use App\Http\Requests\Academic\StoreGradeEntryRequest;
use App\Http\Requests\Academic\UpdateGradeEntryRequest;
use App\Http\Requests\Academic\BulkGradeEntryRequest;
use App\Http\Resources\Academic\GradeEntryResource;
use App\Services\Academic\GradeEntryService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class GradeEntryController extends Controller
{
    protected GradeEntryService $gradeEntryService;

    public function __construct(GradeEntryService $gradeEntryService)
    {
        $this->gradeEntryService = $gradeEntryService;
    }

    /**
     * Display a listing of grade entries
     */
    public function index(Request $request): JsonResponse
    {
        $gradeEntries = $this->gradeEntryService->getGradeEntries($request->all());

        return response()->json([
            'status' => 'success',
            'data' => GradeEntryResource::collection($gradeEntries),
            'meta' => [
                'total' => $gradeEntries->total(),
                'per_page' => $gradeEntries->perPage(),
                'current_page' => $gradeEntries->currentPage(),
                'last_page' => $gradeEntries->lastPage(),
            ]
        ]);
    }

    /**
     * Store a newly created grade entry
     */
    public function store(StoreGradeEntryRequest $request): JsonResponse
    {
        try {
            $gradeEntry = $this->gradeEntryService->createGradeEntry($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Grade entry created successfully',
                'data' => new GradeEntryResource($gradeEntry)
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create grade entry',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Store bulk grade entries
     */
    public function bulkStore(BulkGradeEntryRequest $request): JsonResponse
    {
        try {
            $results = $this->gradeEntryService->createBulkGradeEntries($request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk grade entries processed successfully',
                'data' => [
                    'successful' => $results['successful'],
                    'failed' => $results['failed'],
                    'total' => count($results['successful']) + count($results['failed'])
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to process bulk grade entries',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Display the specified grade entry
     */
    public function show(GradeEntry $gradeEntry): JsonResponse
    {
        $this->authorize('view', $gradeEntry);

        return response()->json([
            'status' => 'success',
            'data' => new GradeEntryResource($gradeEntry->load([
                'student', 'class.subject', 'academicTerm', 'enteredBy', 'modifiedBy'
            ]))
        ]);
    }

    /**
     * Update the specified grade entry
     */
    public function update(UpdateGradeEntryRequest $request, GradeEntry $gradeEntry): JsonResponse
    {
        $this->authorize('update', $gradeEntry);

        try {
            $updatedGradeEntry = $this->gradeEntryService->updateGradeEntry($gradeEntry, $request->validated());

            return response()->json([
                'status' => 'success',
                'message' => 'Grade entry updated successfully',
                'data' => new GradeEntryResource($updatedGradeEntry)
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update grade entry',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Remove the specified grade entry
     */
    public function destroy(GradeEntry $gradeEntry): JsonResponse
    {
        $this->authorize('delete', $gradeEntry);

        try {
            $this->gradeEntryService->deleteGradeEntry($gradeEntry);

            return response()->json([
                'status' => 'success',
                'message' => 'Grade entry deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete grade entry',
                'error' => $e->getMessage()
            ], 422);
        }
    }

    /**
     * Get student grades for a specific term
     */
    public function studentGrades(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
        ]);

        $grades = $this->gradeEntryService->getStudentGrades(
            $request->student_id,
            $request->academic_term_id
        );

        return response()->json([
            'status' => 'success',
            'data' => GradeEntryResource::collection($grades)
        ]);
    }

    /**
     * Get class grades for a specific assessment
     */
    public function classGrades(Request $request): JsonResponse
    {
        $request->validate([
            'class_id' => 'required|exists:classes,id',
            'assessment_name' => 'required|string',
        ]);

        $grades = $this->gradeEntryService->getClassGrades(
            $request->class_id,
            $request->assessment_name
        );

        return response()->json([
            'status' => 'success',
            'data' => GradeEntryResource::collection($grades)
        ]);
    }

    /**
     * Calculate student GPA
     */
    public function calculateGPA(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => 'required|exists:students,id',
            'academic_term_id' => 'required|exists:academic_terms,id',
        ]);

        try {
            $gpa = $this->gradeEntryService->calculateStudentGPA(
                $request->student_id,
                $request->academic_term_id
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'student_id' => $request->student_id,
                    'academic_term_id' => $request->academic_term_id,
                    'gpa' => $gpa
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to calculate GPA',
                'error' => $e->getMessage()
            ], 422);
        }
    }
}
EOF

echo "✅ Academic Management Controllers created successfully!"
echo "📁 Controllers created in: app/Http/Controllers/API/V1/Academic/"
echo "📋 Created controllers:"
echo "   - TeacherController"
echo "   - SubjectController"
echo "   - AcademicClassController"
echo "   - GradingSystemController"
echo "   - GradeEntryController"
echo "🔧 Next: Create Request classes and Resources"
