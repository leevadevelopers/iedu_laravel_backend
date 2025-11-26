<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class TenantInvitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'tenant_id',
        'identifier',
        'type',
        'role',
        'inviter_id',
        'token',
        'status',
        'expires_at',
        'accepted_at',
        'message',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'accepted_at' => 'datetime',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invitation) {
            if (empty($invitation->token)) {
                $invitation->token = Str::random(64);
            }
            if (empty($invitation->expires_at)) {
                $invitation->expires_at = now()->addDays(7); // 7 days expiry
            }
            if (empty($invitation->status)) {
                $invitation->status = 'pending';
            }
        });
    }

    public function tenant()
    {
        return $this->belongsTo(\App\Models\Settings\Tenant::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function canBeAccepted(): bool
    {
        return $this->status === 'pending' && !$this->isExpired();
    }

    public function markAsAccepted(): void
    {
        $this->update([
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);
    }

    public function markAsCancelled(): void
    {
        $this->update(['status' => 'cancelled']);
    }

    public function markAsExpired(): void
    {
        $this->update(['status' => 'expired']);
    }

    /**
     * Validate the generated URL format
     */
    public function validateUrlFormat(): array
    {
        $acceptUrl = $this->getAcceptUrl();
        
        return [
            'url' => $acceptUrl,
            'is_valid' => filter_var($acceptUrl, FILTER_VALIDATE_URL) !== false,
            'has_hash_routing' => strpos($acceptUrl, '/#/') !== false,
            'has_token' => strpos($acceptUrl, 'token=') !== false,
            'url_length' => strlen($acceptUrl),
            'url_parts' => parse_url($acceptUrl)
        ];
    }

    /**
     * Get the accept invitation URL
     */
    public function getAcceptUrl(): string
    {
        // Debug: Log method entry and invitation state
        Log::info('TenantInvitation::getAcceptUrl() called', [
            'invitation_id' => $this->id,
            'token' => $this->token,
            'status' => $this->status,
            'expires_at' => $this->expires_at,
            'can_be_accepted' => $this->canBeAccepted(),
            'is_pending' => $this->isPending(),
            'is_expired' => $this->isExpired()
        ]);

        if (!$this->canBeAccepted()) {
            Log::warning('Invitation cannot be accepted', [
                'invitation_id' => $this->id,
                'status' => $this->status,
                'expires_at' => $this->expires_at
            ]);
            return '';
        }
        
        // Use the same pattern as FormTemplate for consistency
        $frontendUrl = config('app.frontend_url');
        
        // Debug: Log initial frontend URL from config
        Log::info('Initial frontend URL from config', [
            'invitation_id' => $this->id,
            'frontend_url_config' => $frontendUrl,
            'environment' => app()->environment()
        ]);
        
        // Environment-specific URL generation
        if (app()->environment('local', 'development')) {
            // Local development - use localhost with port
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:4200');
            Log::info('Using local/development environment URL', [
                'invitation_id' => $this->id,
                'env_frontend_url' => env('FRONTEND_URL'),
                'final_frontend_url' => $frontendUrl
            ]);
        } elseif (app()->environment('staging')) {
            // Staging environment
            $frontendUrl = env('FRONTEND_URL', 'https://staging.iops.leeva.digital');
            Log::info('Using staging environment URL', [
                'invitation_id' => $this->id,
                'env_frontend_url' => env('FRONTEND_URL'),
                'final_frontend_url' => $frontendUrl
            ]);
        } else {
            // Production environment - use config value
            $frontendUrl = config('app.frontend_url');
            Log::info('Using production environment URL', [
                'invitation_id' => $this->id,
                'frontend_url' => $frontendUrl
            ]);
        }
        
        // Ensure proper URL formatting for hash routing
        $frontendUrl = rtrim($frontendUrl, '/'); // Remove trailing slash if present
        $finalUrl = "{$frontendUrl}/#/accept-invitation?token={$this->token}";
        
        // Debug: Log final URL generation
        Log::info('Final invitation URL generated', [
            'invitation_id' => $this->id,
            'frontend_url' => $frontendUrl,
            'token' => $this->token,
            'final_url' => $finalUrl,
            'url_length' => strlen($finalUrl)
        ]);
        
        return $finalUrl;
    }
} 