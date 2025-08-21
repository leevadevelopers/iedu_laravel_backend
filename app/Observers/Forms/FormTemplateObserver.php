<?php

namespace App\Observers\Forms;

use App\Models\Forms\FormTemplate;
use Illuminate\Support\Facades\Cache;

class FormTemplateObserver
{
    /**
     * Handle the FormTemplate "created" event.
     */
    public function created(FormTemplate $template): void
    {
        $this->clearCache($template);
        
        activity('form_templates')
            ->performedOn($template)
            ->causedBy(auth()->user())
            ->log('Form template created');
    }

    /**
     * Handle the FormTemplate "updated" event.
     */
    public function updated(FormTemplate $template): void
    {
        $this->clearCache($template);
        
        activity('form_templates')
            ->performedOn($template)
            ->causedBy(auth()->user())
            ->withProperties([
                'old' => $template->getOriginal(),
                'new' => $template->getChanges()
            ])
            ->log('Form template updated');
    }

    /**
     * Handle the FormTemplate "deleted" event.
     */
    public function deleted(FormTemplate $template): void
    {
        $this->clearCache($template);
        
        activity('form_templates')
            ->performedOn($template)
            ->causedBy(auth()->user())
            ->log('Form template deleted');
    }

    private function clearCache(FormTemplate $template): void
    {
        $patterns = [
            "org_templates_{$template->tenant_id}_*",
            "form_template_{$template->id}",
            "methodology_templates_{$template->methodology_type}"
        ];
        
        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }
}
