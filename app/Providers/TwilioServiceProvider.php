<?php

namespace App\Providers;

use App\Notifications\Channels\TwilioChannel;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Notification;

class TwilioServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Register the custom Twilio channel
        Notification::extend('twilio', function ($app) {
            return new TwilioChannel();
        });
    }
}
