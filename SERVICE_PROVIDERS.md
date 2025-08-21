# Service Provider Registration

Add these to your `config/app.php` providers array or create dedicated service providers:

## Repository Bindings
```php
// In AppServiceProvider boot() method or dedicated provider
$this->app->bind(
    \App\Repositories\Project\Contracts\ProjectRepositoryInterface::class,
    \App\Repositories\Project\ProjectRepository::class
);

$this->app->bind(
    \App\Repositories\Project\Contracts\ProjectMilestoneRepositoryInterface::class,
    \App\Repositories\Project\ProjectMilestoneRepository::class
);
```

## AI Service Bindings
```php
// AI service bindings with fallbacks
$this->app->bind(
    \App\Contracts\AI\Project\ProjectIntelligenceInterface::class,
    \App\Services\AI\Project\ProjectIntelligenceService::class
);
```

## Route Registration
Add to `routes/api.php`:
```php
Route::middleware(['auth:sanctum', 'tenant'])->prefix('api/v1')->group(function () {
    require base_path('routes/project.php');
});
```

## Form Engine Integration
Ensure your form engine is configured to handle these form types:
- `project_creation`
- `project_update`
- `milestone_creation`
- `milestone_update`

## AI Configuration
Add to `config/ai.php`:
```php
return [
    'enabled' => env('AI_ENABLED', false),
    'project' => [
        'enabled' => env('AI_PROJECT_ENABLED', true),
        'provider' => env('AI_PROVIDER', 'openai'),
        'models' => [
            'analysis' => env('AI_ANALYSIS_MODEL', 'gpt-4'),
            'insights' => env('AI_INSIGHTS_MODEL', 'gpt-3.5-turbo'),
        ]
    ]
];
```

## Environment Variables
Add to `.env`:
```
# AI Configuration
AI_ENABLED=false
AI_PROJECT_ENABLED=true
AI_PROVIDER=openai
AI_OPENAI_KEY=your_openai_key_here

# Project Module
PROJECT_DEFAULT_CURRENCY=USD
PROJECT_MAX_BUDGET=99999999.99
PROJECT_MIN_BUDGET=1000
```

## Composer Autoload
After running this script, make sure to run:
```bash
composer dump-autoload
```

## Database Migration
Run the migration script to create all necessary tables:
```bash
# Create and run the migration setup script first
./setup_project_migrations.sh
php artisan migrate
```

## Seeding Test Data
```bash
php artisan db:seed --class=ProjectSeeder
```
