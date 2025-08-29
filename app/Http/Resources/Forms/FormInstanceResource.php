<?php

namespace App\Http\Resources\Forms;

use Illuminate\Http\Resources\Json\JsonResource;

class FormInstanceResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'template_name' => $this->template->name,
            'template_category' => $this->template->category,
            'status' => $this->status,
            'submission_type' => $this->submission_type,
            'form_data' => $this->form_data,
            'validation_errors' => $this->validation_errors,
            'workflow_status' => $this->workflow_status,
            'workflow_step' => $this->workflow_step,
            'workflow_data' => $this->workflow_data,
            'submitted_at' => $this->submitted_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),
            'tenant_id' => $this->tenant_id,
            'created_by' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->name,
                    'email' => $this->creator->email
                ];
            }),
            'updated_by' => $this->whenLoaded('updater', function () {
                return [
                    'id' => $this->updater->id,
                    'name' => $this->updater->name,
                    'email' => $this->updater->email
                ];
            }),
            'template' => $this->whenLoaded('template', function () {
                return new FormTemplateResource($this->template);
            })
        ];
    }
}
