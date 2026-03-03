<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\NLPIntentClassifierService;
use App\Services\EntityExtractorService;

class NLPServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(NLPIntentClassifierService::class, function ($app) {
            return new NLPIntentClassifierService();
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
