<?php

namespace App\Providers;

use App\Services\MLMicroserviceClient;
use Illuminate\Support\ServiceProvider;

class NLPServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(MLMicroserviceClient::class, function ($app) {
            return new MLMicroserviceClient();
        });
    }
    
    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Services are registered in the register method
    }
}
