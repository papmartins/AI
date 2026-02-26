<?php

namespace App\Exceptions;

use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class Handler extends ExceptionHandler
{
    /**
     * The list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     */
    public function register(): void
    {
        $this->reportable(function (Throwable $e) {
            //
        });

        $this->renderable(function (NotFoundHttpException $e, Request $request) {
            return $this->handleLocaleRedirect($e, $request);
        });
    }

    /**
     * Handle locale redirect for 404 errors.
     *
     * @param \Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\Response|mixed
     */
    protected function handleLocaleRedirect(NotFoundHttpException $e, Request $request)
    {
        // List of routes that should NOT be redirected (auth routes, API routes, etc.)
        $excludedRoutes = [
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
        ];

        // Check if this is an excluded route
        $isExcluded = false;
        foreach ($excludedRoutes as $route) {
            if ($request->is($route)) {
                $isExcluded = true;
                break;
            }
        }

        // Also exclude API and Sanctum routes
        if (str_starts_with($request->path(), 'api/') || str_starts_with($request->path(), 'sanctum/')) {
            $isExcluded = true;
        }

        // If this is not an excluded route and might be missing locale prefix
        if (!$isExcluded && !$request->is('/')) {
            $firstSegment = $request->segment(1);
            $supportedLocales = ['en', 'pt', 'es'];
            
            // If the first segment is not a supported locale, redirect with 'en' prefix
            if (!in_array($firstSegment, $supportedLocales)) {
                return redirect()->to('/en/' . ltrim($request->path(), '/'));
            }
        }

        // If we can't handle it, return the original exception response
        return null;
    }
}
