<?php

namespace App\Events;

use App\Models\Settings\Tenant;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TenantCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Tenant $tenant,
        public User $owner,
        public array $context = []
    ) {}
}

