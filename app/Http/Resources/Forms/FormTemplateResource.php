<?php

namespace App\Http\Resources\Forms;

namespace App\Http\Resources\Forms;

use Illuminate\Http\Resources\Json\JsonResource;

class FormTemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'version' => $this->version,
            'category' => $this->category,
            'methodology_type' => $this->methodology_type,
            'estimated_completion_time' => $this->estimated_completion_time,
            'estimated_duration_minutes' => $this->getEstimatedDurationInMinutes(),
            'is_multi_step' => $this->is_multi_step,
            'auto_save' => $this->auto_save,
            'compliance_level' => $this->compliance_level,
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'metadata' => $this->metadata,
            'form_configuration' => $this->form_configuration,
            'steps' => $this->steps,
            'step_count' => count($this->steps ?? []),
            'field_count' => $this->getTotalFieldCount(),
            'form_triggers' => $this->form_triggers,
            'validation_rules' => $this->validation_rules,
            'workflow_configuration' => $this->workflow_configuration,
            'ai_prompts' => $this->ai_prompts,
            'creator' => [
                'id' => $this->creator->id,
                'name' => $this->creator->name
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'usage_stats' => $this->when($request->include_stats, function () {
                return [
                    'total_instances' => $this->instances()->count(),
                    'completed_instances' => $this->instances()->completed()->count(),
                    'draft_instances' => $this->instances()->where('status', 'draft')->count()
                ];
            })
        ];
    }

    private function getTotalFieldCount(): int
    {
        $count = 0;
        foreach ($this->steps ?? [] as $step) {
            foreach ($step['sections'] ?? [] as $section) {
                $count += count($section['fields'] ?? []);
            }
        }
        return $count;
    }
}

