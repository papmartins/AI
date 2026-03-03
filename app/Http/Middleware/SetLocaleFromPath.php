<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;

class SetLocaleFromPath
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
        // List of supported languages
        $supportedLanguages = ['en', 'pt', 'es'];
        
        // Check if language is explicitly set in X-Locale header (highest priority for APIs)
        $headerLocale = $request->header('X-Locale');
        if (!empty($headerLocale) && in_array($headerLocale, $supportedLanguages)) {
            App::setLocale($headerLocale);
            $request->attributes->add(['locale' => $headerLocale]);
            if ($request->hasSession()) {
                Session::put('locale', $headerLocale);
            }
            return $next($request);
        }
        
        // Check if language is explicitly set in request (for language switching)
        if ($request->has('lang')) {
            $lang = $request->input('lang');
            if (in_array($lang, $supportedLanguages)) {
                App::setLocale($lang);
                $request->attributes->add(['locale' => $lang]);
                if ($request->hasSession()) {
                    Session::put('locale', $lang);
                }
                return $next($request);
            }
        }
        
        // Check URL prefix for language (for web routes with locale prefix)
        $firstSegment = $request->segment(1);
        if (in_array($firstSegment, $supportedLanguages)) {
            App::setLocale($firstSegment);
            $request->attributes->add(['locale' => $firstSegment]);
            if ($request->hasSession()) {
                Session::put('locale', $firstSegment);
            }
            return $next($request);
        }
        
        // For API routes without locale prefix, check Accept-Language header
        if ($request->is('api/*')) {
            $acceptLanguage = $request->header('Accept-Language', '');
            
            // Parse Accept-Language header
            if (!empty($acceptLanguage)) {
                foreach ($supportedLanguages as $lang) {
                    if (str_contains(strtolower($acceptLanguage), $lang)) {
                        App::setLocale($lang);
                        $request->attributes->add(['locale' => $lang]);
                        return $next($request);
                    }
                }
            }
            
            // Default to Portuguese for API routes
            App::setLocale('pt');
            $request->attributes->add(['locale' => 'pt']);
        }
        
        return $next($request);
    }
}
