<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\Api\Forms\FormTemplateController;
use App\Http\Controllers\Api\Forms\FormInstanceController;
use Illuminate\Support\Facades\Route;

require_once __DIR__ . '/project.php';

// Auth routes
Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);
    Route::post('register', [AuthController::class, 'register']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('reset-password', [PasswordController::class, 'reset']);
    Route::middleware('auth:api')->group(function () {
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::post('me', [AuthController::class, 'me']);
        Route::post('change-password', [PasswordController::class, 'change']);
    });
});

// Tenant routes (protected)
Route::prefix('tenants')->middleware('auth:api', 'tenant')->group(function () {
    Route::get('/', [TenantController::class, 'index']);
    Route::post('/', [TenantController::class, 'store']);
    Route::post('switch', [TenantController::class, 'switch']);
    Route::middleware('tenant')->group(function () {
        Route::get('current', [TenantController::class, 'show']);
        Route::get('users', [TenantController::class, 'users']);
        Route::post('users', [TenantController::class, 'addUser']);
        Route::delete('users/{user}', [TenantController::class, 'removeUser']);
        Route::put('users/{user}/role', [TenantController::class, 'updateUserRole']);
        Route::get('settings', [TenantController::class, 'settings']);
        Route::put('settings', [TenantController::class, 'updateSettings']);
    });
});

// Other protected API resources
Route::middleware(['auth:api', 'tenant'])->group(function () {
    Route::apiResource('projects', ProjectController::class);
});

Route::get('test', function () {
    return response()->json(['message' => 'API is working']);
});


Route::middleware(['auth:api', 'tenant'])->group(function () {
    
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
        
        // Methodology support
        Route::get('/methodology/{methodology}/requirements', [FormTemplateController::class, 'methodologyRequirements']);
        Route::post('/preview-adaptation', [FormTemplateController::class, 'previewAdaptation']);
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
        
        // Workflow operations
        Route::get('/{instance}/workflow', [FormInstanceController::class, 'workflow']);
        Route::post('/{instance}/workflow', [FormInstanceController::class, 'workflowAction']);
    });
});
