<?php
namespace App\Http\Controllers;

use App\Models\Movie;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WishlistController extends Controller
{
    public function index()
    {
        $movies = Movie::whereHas('wishlist', fn($q) => $q->where('user_id', auth()->id()))
            ->with('genre')
            ->paginate(12);

        return Inertia::render('Wishlist/Index', compact('movies'));
    }

    public function toggle(Request $request, Movie $movie)
    {
        $userId = auth()->id();
        
        if (auth()->user()->wishlist()->where('movie_id', $movie->id)->exists()) {
            auth()->user()->wishlist()->detach($movie->id);
            $message = 'Removed from wishlist';
        } else {
            auth()->user()->wishlist()->attach($movie->id);
            $message = 'Added to wishlist';
        }

        return back()->with('success', $message);
    }
}
