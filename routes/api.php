<?php

use App\Http\Controllers\IrisController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::delete('/users/cache/clear', [UserController::class, 'clearCache'])->name('users.cache.clear');
});

Route::post('/iris/predict', [IrisController::class, 'predict']);

// Anomaly Detection API
Route::prefix('anomaly')->group(function () {
    Route::get('/stats', [\App\Http\Controllers\AnomalyController::class, 'stats']);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/check-me', [\App\Http\Controllers\AnomalyController::class, 'checkMe']);
        Route::get('/', [\App\Http\Controllers\AnomalyController::class, 'index']);
        Route::get('/users/{userId}', [\App\Http\Controllers\AnomalyController::class, 'userAnomalies']);
        Route::post('/resolve/{anomalyId}', [\App\Http\Controllers\AnomalyController::class, 'resolve']);
        Route::post('/retrain', [\App\Http\Controllers\AnomalyController::class, 'retrain']);
    });
});

// Movie Recommendation API
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/recommendations/personalized', [\App\Http\Controllers\RecommendationController::class, 'personalized']);
    Route::get('/recommendations/popular', [\App\Http\Controllers\RecommendationController::class, 'popular']);
    Route::post('/recommendations/retrain', [\App\Http\Controllers\RecommendationController::class, 'retrain']);
});

// Public recommendations endpoint (no auth required)
Route::get('/recommendations/popular-public', [\App\Http\Controllers\RecommendationController::class, 'popular']);