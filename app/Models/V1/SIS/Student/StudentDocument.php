<?php

namespace App\Models\V1\SIS\Student;

use App\Models\V1\SIS\School\School;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Student Document Model
 *
 * Manages documents related to students including enrollment forms,
 * medical records, and other educational documents.
 *
 * @property int $id
 * @property int $school_id
 * @property int $student_id
 * @property string $document_name
 * @property string $document_type
 * @property string|null $document_category
 * @property string $file_name
 * @property string $file_path
 * @property string $file_type
 * @property int $file_size
 * @property string $mime_type
 * @property string $status
 * @property string|null $expiration_date
 * @property bool $required
 * @property bool $verified
 * @property int $uploaded_by
 * @property int|null $verified_by
 * @property \Illuminate\Support\Carbon|null $verified_at
 * @property string|null $verification_notes
 * @property array|null $access_permissions_json
 * @property bool $ferpa_protected
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property \Illuminate\Support\Carbon|null $deleted_at
 */
class StudentDocument extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'student_documents';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'document_name',
        'document_type',
        'document_category',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
        'mime_type',
        'status',
        'expiration_date',
        'required',
        'verified',
        'uploaded_by',
        'verified_by',
        'verified_at',
        'verification_notes',
        'access_permissions_json',
        'ferpa_protected',
    ];

    /**
     * The attributes that should be cast to native types.
     */
    protected $casts = [
        'expiration_date' => 'date',
        'required' => 'boolean',
        'verified' => 'boolean',
        'verified_at' => 'datetime',
        'access_permissions_json' => 'array',
        'ferpa_protected' => 'boolean',
        'file_size' => 'integer',
    ];

    /**
     * Get the school that owns the document.
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * Get the student that owns the document.
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * Get the user who uploaded the document.
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the user who verified the document.
     */
    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Check if document is expired.
     */
    public function isExpired(): bool
    {
        return $this->expiration_date && Carbon::parse($this->expiration_date)->isPast();
    }

    /**
     * Check if document is verified.
     */
    public function isVerified(): bool
    {
        return $this->verified && $this->status === 'approved';
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSize(): string
    {
        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get document download URL.
     */
    public function getDownloadUrl(): string
    {
        return Storage::url($this->file_path);
    }

    /**
     * Check if user can access this document.
     */
    public function canBeAccessedBy(User $user): bool
    {
        // System admin or school admin can access all documents
        if ($user->hasRole(['super_admin', 'school_admin'])) {
            return true;
        }

        // Teachers can access non-FERPA protected documents
        if ($user->hasRole('teacher') && !$this->ferpa_protected) {
            return true;
        }

        // Parents can access their child's documents if they have academic access
        if ($user->hasRole('parent')) {
            return $this->student->familyRelationships()
                ->where('guardian_user_id', $user->id)
                ->where('academic_access', true)
                ->exists();
        }

        return false;
    }

    /**
     * Scope to filter verified documents.
     */
    public function scopeVerified($query)
    {
        return $query->where('verified', true)
                    ->where('status', 'approved');
    }

    /**
     * Scope to filter required documents.
     */
    public function scopeRequired($query)
    {
        return $query->where('required', true);
    }

    /**
     * Scope to filter by document type.
     */
    public function scopeByType($query, string $type)
    {
        return $query->where('document_type', $type);
    }

    /**
     * Scope to filter expired documents.
     */
    public function scopeExpired($query)
    {
        return $query->whereNotNull('expiration_date')
                    ->where('expiration_date', '<', now());
    }
}
