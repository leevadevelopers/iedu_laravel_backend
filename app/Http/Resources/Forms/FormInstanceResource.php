<?php

namespace App\Http\Resources\Forms;

use Illuminate\Http\Resources\Json\JsonResource;

class FormInstanceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'instance_code' => $this->instance_code,
            'form_template' => [
                'id' => $this->template->id,
                'name' => $this->template->name,
                'category' => $this->template->category,
                'methodology_type' => $this->template->methodology_type,
                'estimated_completion_time' => $this->template->estimated_completion_time
            ],
            'user' => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email
            ],
            'form_data' => $this->form_data,
            'calculated_fields' => $this->calculated_fields,
            'status' => $this->status,
            'current_step' => $this->current_step,
            'completion_percentage' => $this->completion_percentage,
            'workflow_state' => $this->workflow_state ? json_decode($this->workflow_state, true) : null,
            'workflow_history' => $this->workflow_history,
            'validation_results' => $this->validation_results,
            'compliance_results' => $this->compliance_results,
            'can_edit' => $this->canBeEditedBy(auth()->user()),
            'is_draft' => $this->isDraft(),
            'is_submitted' => $this->isSubmitted(),
            'is_completed' => $this->isCompleted(),
            'submitted_at' => $this->submitted_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'submissions' => $this->when($this->relationLoaded('submissions'), function () {
                return $this->submissions->map(function ($submission) {
                    return [
                        'id' => $submission->id,
                        'submission_type' => $submission->submission_type,
                        'submitter' => $submission->submitter->name,
                        'notes' => $submission->notes,
                        'created_at' => $submission->created_at->toISOString()
                    ];
                });
            })
        ];
    }
}