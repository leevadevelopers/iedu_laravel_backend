<?php

namespace App\Services\V1\Workflow\Schedule;

use App\Models\V1\Schedule\Schedule;
use App\Models\V1\Schedule\Lesson;
use App\Services\WorkflowService;

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
    public function createScheduleChangeRequest(array $data)
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
    public function createExtraLessonRequest(array $data)
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
    public function createLessonCancellationRequest(array $data)
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
    public function createAttendanceAppeal(array $data)
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

        if ($result && $workflow->status === 'approved') {
            // Execute the approved action
            $this->executeApprovedWorkflow($workflow->toArray());
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
