<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Mitigate HTTPoxy vulnerability (CVE-2016-5385 / CVE-2016-5387)
        if (isset($_SERVER['HTTP_PROXY'])) {
            unset($_SERVER['HTTP_PROXY']);
            unset($_ENV['HTTP_PROXY']);
            putenv('HTTP_PROXY');
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        if ($this->app->isProduction() || request()->isSecure()) {
            URL::forceScheme('https');
        }
    }
}
