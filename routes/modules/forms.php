<?php

use App\Http\Controllers\API\V1\Forms\FormInstanceController;
use App\Http\Controllers\API\V1\Forms\FormTemplateController;
use App\Http\Controllers\API\V1\Forms\PublicFormController;
use App\Http\Controllers\API\V1\Forms\PublicFormTemplateController;
use Illuminate\Support\Facades\Route;

// Public Form Template Routes (no authentication required)
Route::prefix('public/forms')->group(function () {
    Route::get('/{token}', [PublicFormTemplateController::class, 'show']);
    Route::post('/{token}/create-instance', [PublicFormTemplateController::class, 'createInstance']);
    Route::put('/{token}/update-instance', [PublicFormTemplateController::class, 'updateInstance']);
    Route::post('/{token}/submit-instance', [PublicFormTemplateController::class, 'submitInstance']);
    Route::post('/{token}/validate-instance', [PublicFormTemplateController::class, 'validateInstance']);
});

// Forms API Routes
// Route::middleware(['auth:api', 'tenant'])->group(function () {
    Route::middleware(['auth:api'])->group(function () {

    // Form Templates
    Route::prefix('form-templates')->group(function () {
        Route::get('/', [FormTemplateController::class, 'index']);
        Route::post('/', [FormTemplateController::class, 'store']);
        Route::get('/{template}', [FormTemplateController::class, 'show']);
        Route::put('/{template}', [FormTemplateController::class, 'update']);
        Route::delete('/{template}', [FormTemplateController::class, 'destroy']);

        // Template operations
        Route::post('/{template}/duplicate', [FormTemplateController::class, 'duplicate']);
        Route::post('/{template}/customize', [FormTemplateController::class, 'customize']);
        Route::get('/{template}/versions', [FormTemplateController::class, 'versions']);
        Route::post('/{template}/versions/{versionId}/restore', [FormTemplateController::class, 'restoreVersion']);

        // Export/Import operations
        Route::get('/{template}/export', [FormTemplateController::class, 'export']);
        Route::post('/import', [FormTemplateController::class, 'import']);

        // Deleted templates management (admin only)
        Route::get('/deleted/list', [FormTemplateController::class, 'deletedList']);
        Route::get('/deleted/count', [FormTemplateController::class, 'deletedCount']);
        Route::post('/{template}/restore', [FormTemplateController::class, 'restore']);
        Route::delete('/{template}/force', [FormTemplateController::class, 'forceDelete']);

        // Debug routes (remove in production)
        Route::get('/debug/clear-cache', [FormTemplateController::class, 'debugClearCache']);
        Route::get('/debug/check-templates', [FormTemplateController::class, 'debugCheckTemplates']);
        Route::get('/debug/test-scope', [FormTemplateController::class, 'debugTestGlobalScope']);

        // Public access operations
        Route::post('/{template}/public-token', [FormTemplateController::class, 'generatePublicToken']);
        Route::delete('/{template}/public-token', [FormTemplateController::class, 'revokePublicToken']);
        Route::put('/{template}/public-settings', [FormTemplateController::class, 'updatePublicSettings']);
        Route::get('/{template}/public-submissions', [FormTemplateController::class, 'getPublicSubmissions']);


    });

    // Form Instances
    Route::prefix('form-instances')->group(function () {
        Route::get('/', [FormInstanceController::class, 'index']);
        Route::post('/', [FormInstanceController::class, 'store']);
        Route::get('/{instance}', [FormInstanceController::class, 'show']);
        Route::put('/{instance}', [FormInstanceController::class, 'update']);
        Route::delete('/{instance}', [FormInstanceController::class, 'destroy']);

        // Form operations
        Route::post('/{instance}/submit', [FormInstanceController::class, 'submit']);
        Route::post('/{instance}/auto-save', [FormInstanceController::class, 'autoSave']);
        Route::get('/{instance}/validate', [FormInstanceController::class, 'validate']);
        Route::post('/{instance}/field-suggestions', [FormInstanceController::class, 'fieldSuggestions']);

        // Public access operations
        Route::post('/{instance}/public-token', [FormInstanceController::class, 'generatePublicToken']);
        Route::delete('/{instance}/public-token', [FormInstanceController::class, 'revokePublicToken']);

        // Workflow operations
        Route::get('/{instance}/workflow', [FormInstanceController::class, 'workflow']);
        Route::post('/{instance}/workflow', [FormInstanceController::class, 'workflowAction']);

        // Direct approval/rejection operations
        Route::post('/{instance}/approve', [FormInstanceController::class, 'approve']);
        Route::post('/{instance}/reject', [FormInstanceController::class, 'reject']);
    });
});
