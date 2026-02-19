<?php

namespace App\Http\Controllers;

use App\Services\MovieRecommender;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RecommendationController extends Controller
{
    public function __construct(protected MovieRecommender $recommender)
    {
    }

    /**
     * Get personalized recommendations for authenticated user
     */
    public function personalized(Request $request)
    {
        $user = $request->user();
        $limit = $request->input('limit', 6);
        
        $recommendations = $this->recommender->recommendForUser($user, $limit);
        
        return response()->json([
            'success' => true,
            'recommendations' => $recommendations,
            'user_id' => $user->id,
            'algorithm' => 'collaborative_filtering_knn',
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * Get global popular recommendations
     */
    public function popular(Request $request)
    {
        $limit = $request->input('limit', 6);
        
        $recommendations = $this->recommender->getPopularRecommendations($limit);
        
        return response()->json([
            'success' => true,
            'recommendations' => $recommendations,
            'algorithm' => 'popularity_based',
            'timestamp' => now()->toDateTimeString()
        ]);
    }

    /**
     * Retrain the recommendation model
     */
    public function retrain(Request $request)
    {
        $this->authorize('retrain-models', User::class);
        
        $result = $this->recommender->retrain();
        
        return response()->json([
            'success' => true,
            'message' => 'Recommendation model retrained successfully',
            'training_stats' => $result
        ]);
    }

    /**
     * Show recommendations page (Inertia)
     */
    public function show(Request $request)
    {
        $user = $request->user();
        
        return Inertia::render('Recommendations/Index', [
            'personalizedRecommendations' => $this->recommender->recommendForUser($user, 12),
            'popularRecommendations' => $this->recommender->getPopularRecommendations(12),
        ]);
    }
}