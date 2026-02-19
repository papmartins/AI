<?php

namespace App\Http\Controllers;

use App\Services\MovieRecommender;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function __construct(protected MovieRecommender $recommender)
    {
    }

    public function index(Request $request)
    {
        // Get recommendations for the dashboard
        if (auth()->check()) {
            $recommendations = $this->recommender->recommendForUser(auth()->user(), 6);
            $recommendations = array_map(function($item) { return $item['movie']; }, $recommendations);
        } else {
            $recommendations = $this->recommender->getPopularRecommendations(6);
            $recommendations = array_map(function($item) { return $item['movie']; }, $recommendations);
        }

        return Inertia::render('Dashboard', [
            'recommendations' => $recommendations,
            'usingMLRecommendations' => auth()->check(),
        ]);
    }
}