<?php

namespace App\Providers;

use App\Models\Forms\FormTemplate;
use App\Models\Forms\FormInstance;
use App\Policies\Forms\FormTemplatePolicy;
use App\Policies\Forms\FormInstancePolicy;
use App\Services\Forms\FormIntelligenceService;
use App\Services\Forms\FormTemplateService;
use App\Services\Forms\FormPatternEngine;
use App\Services\Forms\FormRuleEngine;
use App\Services\Forms\WorkflowIntegrationService;
use App\Services\Forms\Workflow\EducationalWorkflowService;
use App\Services\Forms\Validation\EducationalValidationRules;
use App\Services\Forms\Compliance\EducationalComplianceService;
use App\Services\AI\AIServiceInterface;
use App\Services\AI\NullAIService;
use App\Services\AI\AIServiceFactory;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FormEngineServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind AI Service with lazy resolution to avoid config access during registration
        $this->app->bind(AIServiceInterface::class, function ($app) {
            // Use factory to create the appropriate service when needed
            return AIServiceFactory::create();
        });

        // Form Engine Services - Use lazy loading to prevent circular dependencies
        $this->app->singleton(FormRuleEngine::class, function ($app) {
            return new FormRuleEngine();
        });

        $this->app->singleton(FormPatternEngine::class, function ($app) {
            return new FormPatternEngine();
        });

        $this->app->bind(FormIntelligenceService::class, function ($app) {
            // Use lazy resolution to prevent circular dependencies
            return new FormIntelligenceService(
                $app->make(AIServiceInterface::class),
                $app->bound(FormPatternEngine::class) ? $app->make(FormPatternEngine::class) : null,
                $app->bound(FormRuleEngine::class) ? $app->make(FormRuleEngine::class) : null
            );
        });

        $this->app->bind(FormTemplateService::class, function ($app) {
            return new FormTemplateService();
        });

        $this->app->bind(WorkflowIntegrationService::class, function ($app) {
            return new WorkflowIntegrationService(
                $app->bound(FormRuleEngine::class) ? $app->make(FormRuleEngine::class) : null
            );
        });

        // Educational-specific services
        $this->app->singleton(EducationalWorkflowService::class, function ($app) {
            return new EducationalWorkflowService();
        });

        $this->app->singleton(EducationalComplianceService::class, function ($app) {
            return new EducationalComplianceService();
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register policies
        Gate::policy(FormTemplate::class, FormTemplatePolicy::class);
        Gate::policy(FormInstance::class, FormInstancePolicy::class);

        // Publish configuration
        $this->publishes([
            __DIR__.'/../../config/form_engine.php' => config_path('form_engine.php'),
        ], 'form-engine-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../../database/migrations/form_engine' => database_path('migrations'),
        ], 'form-engine-migrations');
    }
}
