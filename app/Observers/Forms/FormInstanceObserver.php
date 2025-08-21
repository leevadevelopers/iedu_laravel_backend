<?php
namespace App\Observers\Forms;

use App\Models\Forms\FormInstance;

class FormInstanceObserver
{
    /**
     * Handle the FormInstance "created" event.
     */
    public function created(FormInstance $instance): void
    {
        activity('form_instances')
            ->performedOn($instance)
            ->causedBy($instance->user)
            ->withProperties([
                'template_id' => $instance->form_template_id,
                'instance_code' => $instance->instance_code
            ])
            ->log('Form instance created');
    }

    /**
     * Handle the FormInstance "updated" event.
     */
    public function updated(FormInstance $instance): void
    {
        $changes = $instance->getChanges();
        
        // Log different types of updates
        if (isset($changes['status'])) {
            activity('form_instances')
                ->performedOn($instance)
                ->causedBy(auth()->user())
                ->withProperties([
                    'old_status' => $instance->getOriginal('status'),
                    'new_status' => $changes['status']
                ])
                ->log('Form instance status changed');
        }
        
        if (isset($changes['form_data'])) {
            activity('form_instances')
                ->performedOn($instance)
                ->causedBy(auth()->user())
                ->withProperties([
                    'completion_percentage' => $instance->completion_percentage,
                    'current_step' => $instance->current_step
                ])
                ->log('Form instance data updated');
        }
    }

    /**
     * Handle the FormInstance "deleted" event.
     */
    public function deleted(FormInstance $instance): void
    {
        activity('form_instances')
            ->performedOn($instance)
            ->causedBy(auth()->user())
            ->withProperties([
                'instance_code' => $instance->instance_code,
                'status' => $instance->status
            ])
            ->log('Form instance deleted');
    }
}