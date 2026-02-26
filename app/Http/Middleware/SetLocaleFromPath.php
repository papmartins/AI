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
        // Get the first segment of the path
        $firstSegment = $request->segment(1);
        
        // List of supported languages
        $supportedLanguages = ['en', 'pt', 'es'];
        
        // Check if first segment is a language code
        if (in_array($firstSegment, $supportedLanguages)) {
            // Set the language
            App::setLocale($firstSegment);
            Session::put('locale', $firstSegment);
            
            // Store the language in request for later use
            $request->attributes->add(['locale' => $firstSegment]);
        }
        
        return $next($request);
    }
}
