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

        $message = 'Movie rented! Due in 7 days.';
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['message' => $message]);
        }

        return redirect()->back()->with('success', $message);
    }

    public function destroy(Rental $rental)
    {
        // Ensure the rental belongs to the authenticated user
        if ($rental->user_id !== auth()->id()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $rental->returned = true;
        $rental->save();

        return response()->json(['message' => 'Rental returned successfully']);
    }
}
