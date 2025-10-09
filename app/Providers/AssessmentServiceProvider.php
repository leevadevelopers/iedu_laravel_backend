<?php

namespace App\Providers;

use App\Models\Assessment\Assessment;
use App\Models\Assessment\AssessmentSettings;
use App\Models\V1\Academic\GradeEntry;
use App\Models\Assessment\GradeReview;
use App\Models\Assessment\Gradebook;
use App\Policies\Assessment\AssessmentPolicy;
use App\Policies\Assessment\AssessmentSettingsPolicy;
use App\Policies\Assessment\GradeEntryPolicy;
use App\Policies\Assessment\GradeReviewPolicy;
use App\Policies\Assessment\GradebookPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AssessmentServiceProvider extends ServiceProvider
{
    /**
     * All of the container bindings that should be registered.
     *
     * @var array
     */
    public $bindings = [
        // Services can be bound here if needed
    ];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register policies
        $this->registerPolicies();

        // Register model bindings for route model binding
        $this->registerModelBindings();
    }

    /**
     * Register model policies.
     */
    protected function registerPolicies(): void
    {
        $policies = [
            Assessment::class => AssessmentPolicy::class,
            GradeEntry::class => GradeEntryPolicy::class,
            GradeReview::class => GradeReviewPolicy::class,
            Gradebook::class => GradebookPolicy::class,
            AssessmentSettings::class => AssessmentSettingsPolicy::class,
        ];

        foreach ($policies as $model => $policy) {
            Gate::policy($model, $policy);
        }
    }

    /**
     * Register model bindings for route model binding.
     */
    protected function registerModelBindings(): void
    {
        Route::bind('assessment', function ($value) {
            return Assessment::findOrFail($value);
        });

        Route::bind('gradeEntry', function ($value) {
            return \App\Models\V1\Academic\GradeEntry::findOrFail($value);
        });

        Route::bind('gradeReview', function ($value) {
            return GradeReview::findOrFail($value);
        });

        Route::bind('gradebook', function ($value) {
            return Gradebook::findOrFail($value);
        });

        Route::bind('assessmentSetting', function ($value) {
            return AssessmentSettings::findOrFail($value);
        });
    }
}

