<?php 
// File: app/Providers/FormEngineServiceProvider.php
namespace App\Providers;

use App\Models\Forms\FormTemplate;
use App\Models\Forms\FormInstance;
use App\Policies\FormTemplatePolicy;
use App\Policies\FormInstancePolicy;
use App\Services\Forms\FormIntelligenceService;
use App\Services\Forms\FormTemplateService;
use App\Services\Forms\FormPatternEngine;
use App\Services\Forms\FormRuleEngine;
use App\Services\Forms\MethodologyAdapterService;
use App\Services\Forms\WorkflowIntegrationService;
use App\Services\AI\AIServiceInterface;
use App\Services\AI\NullAIService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class FormEngineServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Bind AI Service (with null fallback)
        $this->app->bind(AIServiceInterface::class, function ($app) {
            $provider = config('ai.provider', 'null');
            
            return match($provider) {
                'openai' => $app->make(\App\Services\AI\OpenAIService::class),
                'aws_bedrock' => $app->make(\App\Services\AI\AWSBedrockService::class),
                'azure_openai' => $app->make(\App\Services\AI\AzureOpenAIService::class),
                default => $app->make(NullAIService::class)
            };
        });

        // Form Engine Services
        $this->app->singleton(FormRuleEngine::class);
        $this->app->singleton(FormPatternEngine::class);
        $this->app->singleton(MethodologyAdapterService::class);
        
        $this->app->bind(FormIntelligenceService::class, function ($app) {
            return new FormIntelligenceService(
                $app->make(AIServiceInterface::class),
                $app->make(FormPatternEngine::class),
                $app->make(FormRuleEngine::class)
            );
        });

        $this->app->bind(FormTemplateService::class, function ($app) {
            return new FormTemplateService(
                $app->make(MethodologyAdapterService::class)
            );
        });

        $this->app->bind(WorkflowIntegrationService::class, function ($app) {
            return new WorkflowIntegrationService(
                $app->make(FormRuleEngine::class)
            );
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
