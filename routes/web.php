<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\MovieController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\UserController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Include test translations route for development
if (file_exists(base_path('routes/test-translations.php'))) {
    require base_path('routes/test-translations.php');
}

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

Route::middleware(['auth', 'verified'])->prefix('{locale}')->where(['locale' => 'en|pt|es'])->group(function () {
    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// ONLY path-based routes - no duplication
Route::middleware(['auth', 'verified', 'locale'])->prefix('{locale}')->where(['locale' => 'en|pt|es'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])
        ->name('dashboard');

    // Anomaly Detection
    Route::get('/anomaly-detection', function () {
        $detector = new \App\Services\AnomalyDetector();
        $anomalies = $detector->detectAnomalies();
        $statistics = $detector->getStatistics();
        return Inertia::render('AnomalyDetection', [
            'anomalies' => $anomalies,
            'statistics' => $statistics,
        ]);
    })->name('anomaly.detection');

    // Movies
    Route::get('/movies', [MovieController::class, 'index'])->name('movies.index');
    Route::get('/movies/{movie}', [MovieController::class, 'show'])->name('movies.show');
    Route::post('/movies/{movie}/rate', [RatingController::class, 'store'])->name('ratings.store');

    // Rentals
    Route::get('/rentals', [RentalController::class, 'index'])->name('rentals.index');
    Route::post('/movies/{movie}/rent', [RentalController::class, 'store'])->name('rentals.store');
    Route::delete('/rentals/{rental}', [RentalController::class, 'destroy'])->name('rentals.destroy');

    // Wishlist
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/{movie}/toggle', [WishlistController::class, 'toggle'])->name('wishlist.toggle');

    // Recommendations
    Route::get('/recommendations', [\App\Http\Controllers\RecommendationController::class, 'show'])
        ->name('recommendations.show');

    // Model Training (only the index page - training endpoints moved to API)
    Route::get('/model-training', [\App\Http\Controllers\ModelTrainingController::class, 'index'])
        ->name('model-training.index');

});

require __DIR__.'/auth.php';
