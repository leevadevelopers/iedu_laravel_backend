<?php 
namespace App\Models\Forms;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FormTemplateVersion extends Model
{
    public $timestamps = false;
    
    protected $fillable = [
        'form_template_id', 'version_number', 'changes_summary', 
        'template_data', 'created_by', 'created_at'
    ];

    protected $casts = [
        'template_data' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            $model->created_at = now();
        });
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(FormTemplate::class, 'form_template_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function restore(): FormTemplate
    {
        $template = $this->template;
        $template->update($this->template_data);
        
        return $template;
    }
}