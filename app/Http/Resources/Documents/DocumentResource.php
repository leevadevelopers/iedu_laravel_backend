<?php

namespace App\Http\Resources\Documents;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DocumentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'document_id' => $this->document_id,
            'template' => $this->template,
            'purpose' => $this->purpose,
            'signed_by' => $this->signed_by,
            'notes' => $this->notes,
            'download_url' => $this->download_url,
            'pdf_url' => $this->pdf_url,
            'status' => $this->status,
            'student' => $this->whenLoaded('student', function () {
                return [
                    'id' => $this->student->id,
                    'name' => $this->student->first_name . ' ' . $this->student->last_name,
                ];
            }),
            'generator' => $this->whenLoaded('generator', function () {
                return [
                    'id' => $this->generator->id,
                    'name' => $this->generator->name,
                ];
            }),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}

