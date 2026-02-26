<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocale
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
        // Check if language is set in request (for language switching) - higher priority
        if ($request->has('language')) {
            $language = $request->input('language');
            if (in_array($language, ['en', 'pt', 'es'])) {
                Session::put('locale', $language);
                App::setLocale($language);
            }
        }
        
        // Check if language is set in session
        if (Session::has('locale')) {
            App::setLocale(Session::get('locale'));
        }
        
        return $next($request);
    }
}