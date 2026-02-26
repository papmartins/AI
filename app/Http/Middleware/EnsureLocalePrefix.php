<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class EnsureLocalePrefix
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        // List of routes that should NOT be redirected (auth routes, API routes, etc.)
        $excludedRoutes = [
            // 'login',
            // 'register', 
            'logout',
            'profile.edit',
            'profile.update',
            'profile.destroy',
            'password.request',
            'password.email',
            'password.reset',
            'password.update',
            'verification.notice',
            'verification.verify',
            'verification.send',
            'password.confirm',
            'sanctum.csrf-cookie',
            'ignition.healthCheck',
            'ignition.executeSolution',
            'ignition.updateConfig',
            'api/*',
            'sanctum/*',
        ];
        
        // Check if this is an excluded route
        $isExcluded = false;
        foreach ($excludedRoutes as $route) {
            if ($request->is($route)) {
                $isExcluded = true;
                break;
            }
        }
        
        // If this is not an excluded route
        if (!$isExcluded) {
            $firstSegment = $request->segment(1);
            $supportedLocales = ['en', 'pt', 'es'];
            
            // Check if the URL is just "/" (root)
            if ($request->is('/')) {
                return redirect()->to('/en/dashboard');
            }
            // Check if the first segment is NOT a supported locale
            // This handles cases where the route exists but doesn't have locale prefix
            elseif (!in_array($firstSegment, $supportedLocales) && !empty($firstSegment)) {
                // Always use 'en' as default locale for simplicity
                return redirect()->to('/en/' . $request->path());
            }
        }
        
        return $next($request);
    }
}