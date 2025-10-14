<?php

namespace App\Http\Resources\Assessment;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class GradebookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant_id' => $this->tenant_id,
            'subject_id' => $this->subject_id,
            'class_id' => $this->class_id,
            'term_id' => $this->term_id,
            'title' => $this->title,
            'description' => $this->description,
            'file_path' => $this->file_path,
            'status' => $this->status,
            'uploaded_at' => $this->uploaded_at?->toISOString(),
            'approved_at' => $this->approved_at?->toISOString(),
            'subject' => $this->whenLoaded('subject', function () {
                return [
                    'id' => $this->subject->id,
                    'name' => $this->subject->name,
                ];
            }),
            'class' => $this->whenLoaded('class', function () {
                return [
                    'id' => $this->class->id,
                    'name' => $this->class->name,
                ];
            }),
            'term' => $this->whenLoaded('term', function () {
                return [
                    'id' => $this->term->id,
                    'name' => $this->term->name,
                ];
            }),
            'uploader' => $this->whenLoaded('uploader', function () {
                return [
                    'id' => $this->uploader->id,
                    'name' => $this->uploader->name,
                ];
            }),
            'approver' => $this->whenLoaded('approver', function () {
                return $this->approver ? [
                    'id' => $this->approver->id,
                    'name' => $this->approver->name,
                ] : null;
            }),
            'files' => $this->whenLoaded('files', function () {
                return GradebookFileResource::collection($this->files);
            }),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}

