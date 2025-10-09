<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssessmentSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'academic_term_id' => $this->academic_term_id,
            'assessments_count' => $this->assessments_count,
            'default_passing_score' => (float) $this->default_passing_score,
            'rounding_policy' => $this->rounding_policy,
            'decimal_places' => $this->decimal_places,
            'allow_grade_review' => $this->allow_grade_review,
            'review_deadline_days' => $this->review_deadline_days,
            'config' => $this->config,
            'academic_term' => $this->whenLoaded('academicTerm', function () {
                return $this->academicTerm ? [
                    'id' => $this->academicTerm->id,
                    'name' => $this->academicTerm->name,
                ] : null;
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

