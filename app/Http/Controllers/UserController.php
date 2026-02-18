<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class UserController extends Controller
{
    /**
     * Display a listing of the users with pagination and caching.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index(Request $request)
    {
        $cacheKey = 'users:index:' . md5(serialize($request->all()));
        $cacheDuration = now()->addMinutes(30);
        
        // Try to get cached response
        $cachedResponse = Cache::store('redis')->get($cacheKey);
        
        if ($cachedResponse) {
            return $cachedResponse;
        }
        
        // Build query with search and filters
        $query = User::query();
        
        // Apply search filter
        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->search . '%')
                  ->orWhere('email', 'like', '%' . $request->search . '%');
            });
        }
        
        // Apply email verification filter
        if ($request->has('verified')) {
            $query->whereNotNull('email_verified_at');
        }
        
        // Get paginated results
        $perPage = $request->get('per_page', 15);
        $users = $query->paginate($perPage)->withQueryString();
        
        // Transform using UserResource
        $response = UserResource::collection($users);
        
        // Cache the response
        Cache::store('redis')->put($cacheKey, $response, $cacheDuration);
        
        return $response;
    }
    
    /**
     * Display the specified user.
     *
     * @param  \App\Models\User  $user
     * @return \App\Http\Resources\UserResource
     */
    public function show(User $user)
    {
        $cacheKey = 'users:show:' . $user->id;
        $cacheDuration = now()->addMinutes(30);
        
        // Try to get cached response
        $cachedResponse = Cache::store('redis')->get($cacheKey);
        
        if ($cachedResponse) {
            return $cachedResponse;
        }
        
        // Load relationships
        $user->load(['wishlist', 'rentals']);
        
        // Transform using UserResource
        $response = new UserResource($user);
        
        // Cache the response
        Cache::store('redis')->put($cacheKey, $response, $cacheDuration);
        
        return $response;
    }
    
    /**
     * Clear the cache for users.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function clearCache()
    {
        // Clear all user-related cache keys
        $keys = Cache::store('redis')->keys('users:*');
        
        foreach ($keys as $key) {
            Cache::store('redis')->forget($key);
        }
        
        return response()->json(['message' => 'User cache cleared successfully']);
    }
}