<?php

namespace App\Providers;

use App\Models\Events;
use App\Models\EventsImges;
use App\Models\Purchases;
use App\Models\User;
use App\Observers\AdminDashboardStatsObserver;
use Illuminate\Support\ServiceProvider;
use App\Interfaces\PaymentInterface;
use App\Services\PayPalServices;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentInterface::class, PayPalServices::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        URL::forceScheme('https');

        User::observe(AdminDashboardStatsObserver::class);
        Events::observe(AdminDashboardStatsObserver::class);
        Purchases::observe(AdminDashboardStatsObserver::class);
        EventsImges::observe(AdminDashboardStatsObserver::class);
    }
}
