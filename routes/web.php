<?php

use App\Http\Controllers\MovieController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', [\App\Http\Controllers\DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');

    // Anomaly Detection Dashboard (Admin only)
    Route::get('/anomaly-detection', function () {
        $detector = new \App\Services\AnomalyDetector();
        $anomalies = $detector->detectAnomalies();
        $statistics = $detector->getStatistics();
        
        return Inertia::render('AnomalyDetection', [
            'anomalies' => $anomalies,
            'statistics' => $statistics
        ]);
    })->name('anomaly.detection');
    
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    
    Route::get('/movies', [MovieController::class, 'index'])->name('movies.index');
    Route::get('/movies/{movie}', [MovieController::class, 'show'])->name('movies.show');
    Route::post('/movies/{movie}/rate', [RatingController::class, 'store'])->name('ratings.store');
    Route::delete('/ratings/{rating}', [RatingController::class, 'destroy'])->name('ratings.destroy');
    
    Route::get('/rentals', [RentalController::class, 'index'])->name('rentals.index');
    Route::post('/movies/{movie}/rent', [RentalController::class, 'store'])->name('rentals.store');
    Route::delete('/rentals/{rental}', [RentalController::class, 'destroy'])->name('rentals.destroy');
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/{movie}/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');
    
    Route::get('/recommendations', [\App\Http\Controllers\RecommendationController::class, 'show'])
        ->name('recommendations.show');
    
    Route::post('/web/anomaly/resolve/{anomalyId}', [\App\Http\Controllers\AnomalyController::class, 'resolve'])->name('web.anomaly.resolve');
    Route::post('/web/anomaly/retrain', [\App\Http\Controllers\AnomalyController::class, 'retrain'])->name('web.anomaly.retrain');
    Route::get('/web/anomaly/users/{userId}', [\App\Http\Controllers\AnomalyController::class, 'userAnomalies']);
    
    // Model Training Routes
    Route::get('/model-training', [\App\Http\Controllers\ModelTrainingController::class, 'index'])->name('model-training.index');
    Route::post('/model-training/train-recommendation', [\App\Http\Controllers\ModelTrainingController::class, 'trainRecommendationModel'])->name('model-training.train-recommendation');
    Route::post('/model-training/train-anomaly', [\App\Http\Controllers\ModelTrainingController::class, 'trainAnomalyModel'])->name('model-training.train-anomaly');
    Route::post('/model-training/train-all', [\App\Http\Controllers\ModelTrainingController::class, 'trainAllModels'])->name('model-training.train-all');
    Route::get('/model-training/status', [\App\Http\Controllers\ModelTrainingController::class, 'getTrainingStatus'])->name('model-training.status');

    Route::get('/web/users', [UserController::class, 'index'])->name('users.index');
    Route::get('/web/users/{user}', [UserController::class, 'show'])->name('users.show');
    
    Route::get('/web/admin/users-by-email', function (\Illuminate\Http\Request $request) {
        $user = \App\Models\User::where('email', $request->email)->first();
        
        if ($user) {
            return response()->json(['user' => $user]);
        } else {
            return response()->json(['user' => null], 404);
        }
    });

});
require __DIR__.'/auth.php';
