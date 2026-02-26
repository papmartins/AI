<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     */
    protected function redirectTo(Request $request): ?string
    {
        if ($request->expectsJson()) {
            return null;
        }

        // Check if the request is for a locale-prefixed route
        $locale = $request->segment(1);
        $supportedLocales = ['en', 'pt', 'es'];
        
        if (in_array($locale, $supportedLocales)) {
            // Redirect to the locale-prefixed login route
            return route('login', ['locale' => $locale]);
        }
        
        // Fallback to the non-prefixed login route
        return route('login');
    }
}
