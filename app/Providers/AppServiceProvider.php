<?php

namespace App\Providers;

use App\Models\Notification;
use App\Observers\NotificationObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Notification::observe(NotificationObserver::class);
    }
}
