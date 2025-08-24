<?php
namespace App\Models\Forms;

use App\Models\Traits\Tenantable;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormSubmission extends Model
{
    use Tenantable;
    protected $fillable = [
        'tenant_id', 'form_instance_id', 'submitted_by', 'submission_data',
        'attachments', 'notes', 'submission_type'
    ];

    protected $casts = [
        'submission_data' => 'array',
        'attachments' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function instance(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Forms\FormInstance::class, 'form_instance_id');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by')->withDefault();
    }

    public function isAutoSave(): bool
    {
        return $this->submission_type === 'auto_save';
    }

    public function isFinalSubmission(): bool
    {
        return $this->submission_type === 'submit';
    }
}
