<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    // ... other properties and methods

    /**
     * The application's route middleware groups.
     *
     * @var array
     */
    protected $middlewareGroups = [
        'web' => [
            // ... outros middlewares do grupo web
        ],

        'api' => [
            // Adiciona o middleware de CORS
            \Fruitcake\Cors\HandleCors::class,
            // ... outros middlewares do grupo api
        ],
    ];

    protected $routeMiddleware = [
        // ... other middleware
        'tenant' => \App\Http\Middleware\TenantMiddleware::class,

        'form.session' => \App\Http\Middleware\Forms\FormSessionMiddleware::class,
        'form.validation' => \App\Http\Middleware\Forms\FormValidationMiddleware::class,
        'public.form.access' => \App\Http\Middleware\PublicFormAccessMiddleware::class,

    ];
}