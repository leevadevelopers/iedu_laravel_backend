<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    // ... other properties and methods

    protected $routeMiddleware = [
        // ... other middleware
        'tenant' => \App\Http\Middleware\TenantMiddleware::class,

        'form.session' => \App\Http\Middleware\Forms\FormSessionMiddleware::class,
        'form.validation' => \App\Http\Middleware\Forms\FormValidationMiddleware::class,

    ];
}