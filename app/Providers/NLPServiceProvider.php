<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\IntentClassifierService;
use App\Services\EntityExtractorService;

class NLPServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(IntentClassifierService::class, function ($app) {
            return new IntentClassifierService();
        });
        
        $this->app->singleton(EntityExtractorService::class, function ($app) {
            return new EntityExtractorService();
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
