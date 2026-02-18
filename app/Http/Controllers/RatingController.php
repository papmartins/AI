<?php
namespace App\Http\Controllers;

use App\Models\Movie;
use App\Models\Rating;
use Illuminate\Http\Request;

class RatingController extends Controller
{
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

        $rating->delete();

        if (request()->wantsJson() || request()->ajax()) {
            return response()->json(['message' => 'Rating deleted']);
        }

        return back()->with('success', 'Rating deleted');
    }
}


