<?php

namespace Portavice\CmsSystem;

use Illuminate\Support\ServiceProvider;

class CmsSystemServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('cms-system.php'),
            ], 'config');
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/config.php', 'cms-system');
        $this->app->singleton('cms-system', function () {
            return new CmsSystem();
        });
    }
}
