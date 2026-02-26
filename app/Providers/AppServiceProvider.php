<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Share translations with Inertia - simplified approach
        Inertia::share([
            'translations' => function () {
                $locale = app()->getLocale();
                return [
                    'messages' => (array) (@include resource_path('lang/' . $locale . '/messages.php') ?: []),
                    'auth' => (array) (@include resource_path('lang/' . $locale . '/auth.php') ?: []),
                    'validation' => (array) (@include resource_path('lang/' . $locale . '/validation.php') ?: []),
                ];
            },
        ]);
    }
}
