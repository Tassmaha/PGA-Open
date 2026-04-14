<?php

namespace App\Providers;

use App\Services\ModuleManager;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // ModuleManager en singleton pour check rapide dans tout le code
        $this->app->singleton(ModuleManager::class);
        $this->app->singleton('modules', fn($app) => $app->make(ModuleManager::class));
    }

    public function boot(): void
    {
        //
    }
}
