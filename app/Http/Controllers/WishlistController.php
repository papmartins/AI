<?php
namespace App\Http\Controllers;

use App\Models\Movie;
use App\Services\MovieRecommender;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WishlistController extends Controller
{
    public function __construct(protected MovieRecommender $recommender)
    {
    }
    public function index()
    {
        $userId = auth()->id();
        // Exclude movies already rented by the user
        $rentedIds = auth()->user()->rentals()->where('returned', 0)->pluck('movie_id')->toArray();

        $movies = Movie::whereHas('wishlist', fn($q) => $q->where('user_id', $userId))
            ->whereNotIn('id', $rentedIds)
            ->with('genre')
            ->paginate(12);
        
        $movies->getCollection()->transform(function($movie) {
            $movie->avg_rating = $movie->ratings()->avg('rating');
            return $movie;
        });

        $rentedMovieIds = $rentedIds;

        return Inertia::render('Wishlist/Index', compact('movies', 'rentedMovieIds'));
    }

    public function toggle(Request $request, Movie $movie)
    {
        $userId = auth()->id();
        // use the Wishlist model relation (hasMany) instead of pivot attach/detach
        $wishlistRelation = auth()->user()->wishlist();

            if ($wishlistRelation->where('movie_id', $movie->id)->exists()) {
                $wishlistRelation->where('movie_id', $movie->id)->delete();
                $message = 'Removed from wishlist';
            } else {
                $wishlistRelation->create(['movie_id' => $movie->id]);
                $message = 'Added to wishlist';
            }

            // Clear cache for this user since their wishlist changed
            $this->recommender->clearUserCache(auth()->user());

            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['message' => $message]);
            }

            return redirect()->back()->with('success', $message);
    }
}
