<?php
namespace App\Http\Controllers;

use App\Models\Rental;
use App\Models\Movie;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RentalController extends Controller
{
    public function index()
    {
        $rentals = Rental::with('movie.genre')
            ->where('user_id', auth()->id())
            ->latest()
            ->paginate(12);

        return Inertia::render('Rentals/Index', [
            'rentals' => $rentals
        ]);
    }

    public function store(Request $request, Movie $movie)
    {
        $rental = Rental::create([
            'user_id' => auth()->id(),
            'movie_id' => $movie->id,
            'rented_at' => now(),
            'due_date' => now()->addDays(7),
        ]);

        return back()->with('success', 'Movie rented! Due in 7 days.');
    }
}
