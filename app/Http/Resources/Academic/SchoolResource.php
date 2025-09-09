<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class SchoolResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'school_code' => $this->school_code,
            'official_name' => $this->official_name,
            'display_name' => $this->display_name,
            'short_name' => $this->short_name,
            'school_type' => $this->school_type,
            'educational_levels' => $this->educational_levels,
            'grade_range_min' => $this->grade_range_min,
            'grade_range_max' => $this->grade_range_max,
            'grading_system' => $this->grading_system,
            'academic_calendar_type' => $this->academic_calendar_type,
        ];
    }
}
