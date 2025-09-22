<?php

namespace App\Models\V1\Schedule;

use App\Models\BaseModel;
use App\Models\V1\SIS\School\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;

class LessonContent extends BaseModel
{
    protected $fillable = [
        'school_id', 'lesson_id',
        'title', 'description', 'content_type',
        'file_name', 'file_path', 'file_type', 'file_size', 'mime_type',
        'url', 'thumbnail_url', 'embed_data',
        'category', 'sort_order', 'is_required', 'is_downloadable',
        'is_public', 'access_permissions', 'available_from', 'available_until',
        'metadata', 'notes', 'tags',
        'uploaded_by', 'updated_by'
    ];

    protected $casts = [
        'embed_data' => 'array',
        'access_permissions' => 'array',
        'available_from' => 'date',
        'available_until' => 'date',
        'metadata' => 'array',
        'tags' => 'array',
        'is_required' => 'boolean',
        'is_downloadable' => 'boolean',
        'is_public' => 'boolean',
        'file_size' => 'integer',
        'sort_order' => 'integer'
    ];

    // Relationships
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    // Scopes
    public function scopeRequired(Builder $query): Builder
    {
        return $query->where('is_required', true);
    }

    public function scopeOptional(Builder $query): Builder
    {
        return $query->where('is_required', false);
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query->where('is_public', true);
    }

    public function scopeByType(Builder $query, string $type): Builder
    {
        return $query->where('content_type', $type);
    }

    public function scopeByCategory(Builder $query, string $category): Builder
    {
        return $query->where('category', $category);
    }

    public function scopeAvailable(Builder $query): Builder
    {
        $today = now()->toDateString();
        return $query->where(function ($q) use ($today) {
            $q->where(function ($q2) use ($today) {
                $q2->whereNull('available_from')
                   ->orWhere('available_from', '<=', $today);
            })->where(function ($q2) use ($today) {
                $q2->whereNull('available_until')
                   ->orWhere('available_until', '>=', $today);
            });
        });
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('created_at');
    }

    // Methods
    public function isFile(): bool
    {
        return in_array($this->content_type, [
            'document', 'image', 'audio', 'presentation',
            'worksheet', 'meeting_recording'
        ]);
    }

    public function isUrl(): bool
    {
        return in_array($this->content_type, [
            'video', 'link', 'live_stream', 'external_resource'
        ]);
    }

    public function isRequired(): bool
    {
        return $this->is_required;
    }

    public function isPublic(): bool
    {
        return $this->is_public;
    }

    public function isDownloadable(): bool
    {
        return $this->is_downloadable && $this->isFile();
    }

    public function isAvailable(): bool
    {
        $today = now()->toDateString();

        $availableFrom = $this->available_from ? $this->available_from->lte(now()) : true;
        $availableUntil = $this->available_until ? $this->available_until->gte(now()) : true;

        return $availableFrom && $availableUntil;
    }

    public function getFileUrl(): ?string
    {
        if (!$this->file_path) return null;
        return Storage::url($this->file_path);
    }

    public function getDownloadUrl(): ?string
    {
        if (!$this->isDownloadable()) return null;
        return route('api.v1.lesson-contents.download', $this->id);
    }

    public function getFileSizeFormatted(): ?string
    {
        if (!$this->file_size) return null;

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->file_size;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    public function getContentTypeLabelAttribute(): string
    {
        $labels = [
            'document' => 'Documento',
            'video' => 'Vídeo',
            'audio' => 'Áudio',
            'link' => 'Link',
            'image' => 'Imagem',
            'presentation' => 'Apresentação',
            'worksheet' => 'Atividade',
            'quiz' => 'Questionário',
            'assignment' => 'Tarefa',
            'meeting_recording' => 'Gravação da aula',
            'live_stream' => 'Transmissão ao vivo',
            'external_resource' => 'Recurso externo'
        ];
        return $labels[$this->content_type] ?? ucfirst($this->content_type);
    }

    public function getIconClass(): string
    {
        $icons = [
            'document' => 'fas fa-file-alt',
            'video' => 'fas fa-video',
            'audio' => 'fas fa-volume-up',
            'link' => 'fas fa-external-link-alt',
            'image' => 'fas fa-image',
            'presentation' => 'fas fa-file-powerpoint',
            'worksheet' => 'fas fa-tasks',
            'quiz' => 'fas fa-question-circle',
            'assignment' => 'fas fa-clipboard-list',
            'meeting_recording' => 'fas fa-microphone',
            'live_stream' => 'fas fa-broadcast-tower',
            'external_resource' => 'fas fa-globe'
        ];
        return $icons[$this->content_type] ?? 'fas fa-file';
    }
}
