<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ProjectAnalyticsController;
use App\Http\Controllers\Project\ProjectMilestoneController;
use App\Http\Controllers\Project\ProjectWorkflowController;

// Project CRUD routes
Route::prefix('projects')->group(function () {
    Route::get('/', [ProjectController::class, 'index']);
    Route::post('/', [ProjectController::class, 'store']);
    Route::get('/{id}', [ProjectController::class, 'show']);
    Route::put('/{id}', [ProjectController::class, 'update']);
    Route::delete('/{id}', [ProjectController::class, 'destroy']);
    
    // Project actions
    Route::post('/{id}/approve', [ProjectController::class, 'approve']);
    Route::post('/{id}/activate', [ProjectController::class, 'activate']);
    
    // Project analytics routes
    Route::get('/{id}/dashboard', [ProjectAnalyticsController::class, 'getProjectDashboard']);
    Route::get('/{id}/health', [ProjectAnalyticsController::class, 'getProjectHealth']);
    Route::get('/{id}/progress', [ProjectAnalyticsController::class, 'getProjectProgress']);
    Route::get('/{id}/risks', [ProjectAnalyticsController::class, 'getRiskAnalysis']);
    Route::get('/{id}/budget-analysis', [ProjectAnalyticsController::class, 'getBudgetAnalysis']);
    
    // Project milestone routes
    Route::get('/{id}/milestones', [ProjectMilestoneController::class, 'index']);
    Route::post('/{id}/milestones', [ProjectMilestoneController::class, 'store']);
    Route::put('/{projectId}/milestones/{milestoneId}', [ProjectMilestoneController::class, 'update']);
    Route::post('/{projectId}/milestones/{milestoneId}/complete', [ProjectMilestoneController::class, 'complete']);
    
    // Project workflow routes
    Route::get('/{id}/workflow', [ProjectWorkflowController::class, 'getWorkflowStatus']);
    Route::post('/{id}/workflow/transition', [ProjectWorkflowController::class, 'transitionToNext']);
    Route::get('/{id}/workflow/transitions', [ProjectWorkflowController::class, 'getAvailableTransitions']);
});
