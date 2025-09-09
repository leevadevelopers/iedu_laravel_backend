<?php

namespace App\Http\Resources\Academic;

use Illuminate\Http\Request;

class TeacherResource extends BaseAcademicResource
{
    /**
     * Transform the resource into an array
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim($this->first_name . ' ' . $this->last_name),
            'email' => $this->email,
            'user_type' => $this->user_type,
            'employee_id' => $this->employee_id,
            'status' => $this->status,
        ];
    }
}
