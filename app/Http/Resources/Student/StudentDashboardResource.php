<?php

namespace App\Http\Resources\Student;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'student' => $this->when(isset($this->student), $this->student),
            'today' => $this->when(isset($this->today), $this->today),
            'summary' => $this->when(isset($this->summary), $this->summary),
            'recent_grades' => $this->when(isset($this->recent_grades), $this->recent_grades),
            'upcoming_events' => $this->when(isset($this->upcoming_events), $this->upcoming_events),
        ];
    }
}

