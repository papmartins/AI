<?php
namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\Rating;
use App\Services\MovieRecommender;
use Illuminate\Http\Request;

class RatingController extends Controller
{
    public function __construct(protected MovieRecommender $recommender)
    {
    }
    public function store(Request $request, Movie $movie)
    {
        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
        ]);

        // Check if user already rated this movie
        $existingRating = Rating::where('user_id', auth()->id())
            ->where('movie_id', $movie->id)
            ->first();

        if ($existingRating) {
            // Update existing rating
            $existingRating->update(['rating' => $validated['rating']]);
            $message = 'Rating updated!';
            $rating = $existingRating;
        } else {
            // Create new rating
            $rating = Rating::create([
                'user_id' => auth()->id(),
                'movie_id' => $movie->id,
                'rating' => $validated['rating'],
            ]);
            $message = 'Rating saved!';
        }

        // Load user relation before responding
        $rating->load('user');

        // Clear cache for this user since their preferences changed
        $this->recommender->clearUserCache($rating->user);

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => $message, 'rating' => $rating]);
        }

        return back()->with('success', $message);
    }

    public function destroy(Rating $rating)
    {
        // Ensure user can only delete their own rating
        if ($rating->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Clear cache for this user before deleting
        $this->recommender->clearUserCache($rating->user);
        $rating->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['message' => 'Rating deleted']);
        }

        return back()->with('success', 'Rating deleted');
    }
}


