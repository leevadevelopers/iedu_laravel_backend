<?php

namespace App\Http\Resources\V1\Schedule;

use Illuminate\Http\Request;

class LessonContentResource extends BaseScheduleResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'lesson_id' => $this->lesson_id,

            // Content info
            'title' => $this->title,
            'description' => $this->description,
            'content_type' => $this->content_type,
            'content_type_label' => $this->content_type_label,
            'icon_class' => $this->getIconClass(),

            // File information
            'file_name' => $this->file_name,
            'file_type' => $this->file_type,
            'file_size' => $this->file_size,
            'file_size_formatted' => $this->getFileSizeFormatted(),
            'mime_type' => $this->mime_type,
            'file_url' => $this->getFileUrl(),
            'download_url' => $this->getDownloadUrl(),

            // URL information
            'url' => $this->url,
            'thumbnail_url' => $this->thumbnail_url,
            'embed_data' => $this->embed_data,

            // Organization
            'category' => $this->category,
            'sort_order' => $this->sort_order,
            'is_required' => $this->is_required,
            'is_downloadable' => $this->is_downloadable,
            'is_public' => $this->is_public,

            // Access control
            'available_from' => $this->formatDate($this->available_from),
            'available_until' => $this->formatDate($this->available_until),
            'is_available' => $this->isAvailable(),

            // Metadata
            'metadata' => $this->metadata,
            'notes' => $this->notes,
            'tags' => $this->tags,

            // Flags
            'is_file' => $this->isFile(),
            'is_url' => $this->isUrl(),

            // Audit
            'uploaded_by' => $this->uploaded_by,
            'updated_by' => $this->updated_by,
            'created_at' => $this->formatDateTime($this->created_at),
            'updated_at' => $this->formatDateTime($this->updated_at),
        ];
    }
}
