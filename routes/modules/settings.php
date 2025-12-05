<?php

use App\Http\Controllers\Subscription\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::prefix('settings')
    ->middleware(['auth:api', 'tenant'])
    ->group(function () {
        // Get available subscription packages
        Route::get('subscription-packages', [SubscriptionController::class, 'getPackages']);

        // Subscribe
        Route::post('subscribe', [SubscriptionController::class, 'subscribe']);

        // Subscription management routes
        Route::get('subscriptions', [SubscriptionController::class, 'listSubscriptions']);
        Route::post('subscriptions/{subscription}/extend', [SubscriptionController::class, 'extendSubscription']);
        Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancelSubscription']);
    });

