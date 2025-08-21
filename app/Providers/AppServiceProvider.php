<?php
namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use App\Models\Forms\FormTemplate;
use App\Models\Forms\FormInstance;
use App\Observers\Forms\FormTemplateObserver;
use App\Observers\Forms\FormInstanceObserver;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register services
        $this->app->singleton(\App\Services\TenantService::class);
        $this->app->singleton(\App\Services\ActivityLogService::class);
        $this->app->bind(
            \App\Repositories\Project\Contracts\ProjectRepositoryInterface::class,
            \App\Repositories\Project\ProjectRepository::class
        );
    }

    public function boot(): void
    {
        // Set default string length for schema
        Schema::defaultStringLength(191);
        Scramble::afterOpenApiGenerated(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer', 'JWT')
            );
        });

        // Set tenant context in views
        view()->composer('*', function ($view) {
            if (auth('api')->check()) {
                $tenantId = session('tenant_id', cache()->get('tenant_id_' . auth('api')->id(), 1));
                session(['tenant_id' => $tenantId]);
            }
        });


        FormTemplate::observe(FormTemplateObserver::class);
    FormInstance::observe(FormInstanceObserver::class);

    }
}
