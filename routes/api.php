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

// Anomaly status endpoints for tab display (API with Sanctum auth)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/anomalies/by-status/{status?}', [\App\Http\Controllers\AnomalyController::class, 'getAnomaliesByStatus']);
    Route::get('/anomalies/counts', [\App\Http\Controllers\AnomalyController::class, 'getAnomalyCounts']);
});



Route::middleware('auth:sanctum')->group(function () {
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::delete('/users/cache/clear', [UserController::class, 'clearCache'])->name('users.cache.clear');
});

Route::post('/iris/predict', [IrisController::class, 'predict']);

// Admin API - Find user by email
Route::middleware('auth:sanctum')->get('/admin/users-by-email', function (\Illuminate\Http\Request $request) {
    $user = \App\Models\User::where('email', $request->email)->first();
    
    if ($user) {
        return response()->json(['user' => $user]);
    } else {
        return response()->json(['user' => null], 404);
    }
});

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

// Model Training API (Sanctum authenticated)
Route::middleware('auth:sanctum')->prefix('model-training')->group(function () {
    Route::post('/train-recommendation', [\App\Http\Controllers\ModelTrainingController::class, 'trainRecommendationModel']);
    Route::post('/train-anomaly', [\App\Http\Controllers\ModelTrainingController::class, 'trainAnomalyModel']);
    Route::post('/train-all', [\App\Http\Controllers\ModelTrainingController::class, 'trainAllModels']);
    Route::get('/status', [\App\Http\Controllers\ModelTrainingController::class, 'getTrainingStatus']);
});

Route::post('/login', function (Request $request) {
    $input = json_decode($request->getContent(), true) ?: [];
    
    $credentials = validator($input, [  // ← validator() separado
        'email' => 'required|email',
        'password' => 'required',
    ])->validate();

    if (! Auth::attempt($credentials)) {
        return response()->json(['message' => 'Credenciais inválidas'], 401);
    }

    // Remove esta linha que causa erro: $request->session()->regenerate();

    return response()->json(['message' => 'Login OK']);
});