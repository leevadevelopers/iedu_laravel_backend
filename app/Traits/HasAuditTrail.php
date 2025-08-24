<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasAuditTrail
{
    /**
     * Boot the trait.
     */
    public static function bootHasAuditTrail(): void
    {
        static::creating(function ($model) {
            if (Auth::check()) {
                $model->created_by = $model->created_by ?? Auth::id();
            }
        });

        static::updating(function ($model) {
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    /**
     * Get the user who created the model.
     */
    public function creator(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    /**
     * Get the user who last updated the model.
     */
    public function updater(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    /**
     * Check if the current user created this model.
     */
    public function wasCreatedByCurrentUser(): bool
    {
        return Auth::check() && $this->created_by === Auth::id();
    }

    /**
     * Check if the current user last updated this model.
     */
    public function wasUpdatedByCurrentUser(): bool
    {
        return Auth::check() && $this->updated_by === Auth::id();
    }
}
