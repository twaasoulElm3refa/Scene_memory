<?php

namespace App\Providers;

use App\Interfaces\PaymentInterface;
use App\Models\Event_Tags;
use App\Models\Events;
use App\Models\EventsImges;
use App\Models\ImagesTags;
use App\Models\Purchases;
use App\Models\Tags;
use App\Models\TagsTranslations;
use App\Models\User;
use App\Observers\AdminDashboardStatsObserver;
use App\Observers\EventTagCacheObserver;
use App\Observers\ImageTagCacheObserver;
use App\Observers\TagCacheObserver;
use App\Observers\TagTranslationCacheObserver;
use App\Services\PayPalServices;
use App\Support\Cache\TagSafeCacheManager;
use App\Support\Health\ApplicationReadiness;
use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentInterface::class, PayPalServices::class);
        // Cache::tags() on a store without tag support bypasses the cache instead of throwing.
        $this->app->extend('cache', fn ($manager, $app) => new TagSafeCacheManager($app));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $appUrl = (string) config('app.url');
        URL::forceRootUrl($appUrl);

        if ($scheme = parse_url($appUrl, PHP_URL_SCHEME)) {
            URL::forceScheme($scheme);
        }

        User::observe(AdminDashboardStatsObserver::class);
        Events::observe(AdminDashboardStatsObserver::class);
        Purchases::observe(AdminDashboardStatsObserver::class);
        EventsImges::observe(AdminDashboardStatsObserver::class);
        Tags::observe(TagCacheObserver::class);
        Event_Tags::observe(EventTagCacheObserver::class);
        ImagesTags::observe(ImageTagCacheObserver::class);
        TagsTranslations::observe(TagTranslationCacheObserver::class);

        Event::listen(DiagnosingHealth::class, ApplicationReadiness::class);
    }
}
