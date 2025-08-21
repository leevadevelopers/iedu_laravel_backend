<?php

namespace App\Models\Traits;

use Illuminate\Support\Str;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

trait LogsActivityWithTenant
{
    use LogsActivity;

    protected static function bootLogsActivityWithTenant()
    {
        static::bootLogsActivity();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(\Spatie\Activitylog\Models\Activity $activity, string $eventName)
    {
        $activity->tenant_id = session('tenant_id');
        $activity->event = $eventName;
        
        if (request()->has('batch_uuid') || session()->has('batch_uuid')) {
            $activity->batch_uuid = request()->get('batch_uuid') ?? session('batch_uuid');
        }
        
        if (request()) {
            $activity->ip_address = request()->ip();
            $activity->user_agent = request()->userAgent();
        }
        
        $properties = collect($activity->properties ?? []);
        
        if ($this->shouldLogTenantContext()) {
            $properties = $properties->merge([
                'tenant_context' => [
                    'tenant_id' => session('tenant_id'),
                    'user_role' => auth('api')->user()?->getTenantRoleName(),
                ]
            ]);
        }
        
        $activity->properties = $properties;
    }

    protected function shouldLogTenantContext(): bool
    {
        return session('tenant_id') !== null;
    }

    public function getLogNameToUse(string $eventName = ''): string
    {
        return (property_exists($this, 'logName') && is_string($this->logName) && $this->logName !== '')
            ? $this->logName
            : Str::snake(class_basename($this));
    }
}