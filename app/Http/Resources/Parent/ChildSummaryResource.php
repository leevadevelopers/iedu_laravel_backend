<?php

namespace App\Http\Resources\Parent;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChildSummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'class' => $this->class,
            'photo_url' => $this->photo_url,
            'summary' => [
                'attendance_percentage' => $this->summary['attendance_percentage'] ?? 0,
                'average_grade' => $this->summary['average_grade'] ?? null,
                'fees_status' => $this->summary['fees_status'] ?? 'unknown',
                'fees_balance' => $this->summary['fees_balance'] ?? 0,
                'recent_grades' => $this->summary['recent_grades'] ?? [],
                'upcoming_events' => $this->summary['upcoming_events'] ?? [],
            ],
        ];
    }
}

