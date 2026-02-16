<template>
  <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 py-12">
    <!-- Back Button -->
    <div class="max-w-4xl mx-auto px-6">
      <Link 
        href="/movies" 
        class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800 font-medium mb-12 transition-colors"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back to Movies
      </Link>
    </div>

    <!-- Movie Details -->
    <div class="max-w-6xl mx-auto px-6">
      <div class="bg-white rounded-2xl shadow-2xl overflow-hidden">
        <div class="md:flex">
          <!-- Left: Info + Actions -->
          <div class="md:w-2/3 p-12">
            <div class="space-y-6">
              <!-- Title & Meta -->
              <div>
                <h1 class="text-4xl md:text-5xl font-bold text-gray-900 mb-4 leading-tight">
                  {{ movie.title }}
                </h1>
                <div class="flex flex-wrap items-center gap-4 text-lg text-gray-600 mb-6">
                  <span class="px-4 py-2 bg-indigo-100 text-indigo-800 rounded-full font-semibold">
                    {{ movie.year }}
                  </span>
                  <span class="px-4 py-2 bg-blue-100 text-blue-800 rounded-full">
                    {{ movie.genre.name }}
                  </span>
                  <div class="flex items-center gap-2">
                    <span class="text-2xl">⭐</span>
                    <span class="text-2xl font-bold text-yellow-500">{{ avgRating }}</span>
                    <span class="text-gray-500">({{ movie.ratings.length }})</span>
                  </div>
                </div>
              </div>

              <!-- Description -->
              <p class="text-xl text-gray-700 leading-relaxed">{{ movie.description }}</p>

              <!-- Price & Stock -->
              <div class="flex flex-wrap items-center gap-6 p-6 bg-gray-50 rounded-xl">
                <div class="flex items-center gap-3">
                  <span class="text-4xl font-bold text-green-600">${{ movie.price }}</span>
                  <span class="text-sm text-gray-500">per rental</span>
                </div>
                <div class="flex items-center gap-2">
                  <div class="w-6 h-6 bg-green-500 rounded-full"></div>
                  <span class="font-semibold text-green-700">In Stock: {{ movie.stock }}</span>
                </div>
              </div>

              <!-- Rent Button -->
              <div class="flex gap-4">
                <form @submit.prevent="rentMovie(movie.id)" class="flex-1">
                  <button type="submit" class="bg-gradient-to-r from-green-600 to-emerald-600 text-white py-4 px-8 rounded-xl font-semibold hover:shadow-lg">
                    🎬 Rent Now - ${{ movie.price }}
                  </button>
                </form>

                <button @click="toggleWishlist(movie.id)" class="px-8 py-4 border-2 border-indigo-300 rounded-xl text-indigo-600 font-semibold hover:bg-indigo-50">
                  {{ isInWishlist(movie.id) ? '❤️ Remove' : '💖 Wishlist' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Right: Ratings -->
          <div class="md:w-1/3 bg-gradient-to-b from-indigo-50 to-blue-50 p-12">
            <h3 class="text-2xl font-bold text-gray-900 mb-8">Ratings ({{ movie.ratings.length }})</h3>
            
            <div v-if="movie.ratings.length" class="space-y-4 max-h-96 overflow-y-auto">
              <div 
                v-for="rating in movie.ratings.slice(0, 8)" 
                :key="rating.id" 
                class="flex items-start gap-4 p-4 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow"
              >
                <div class="flex items-center gap-1 flex-shrink-0">
                  <span v-for="n in rating.rating" :key="n" class="text-yellow-400 text-xl">⭐</span>
                  <span v-for="n in (5 - rating.rating)" :key="'e' + n" class="text-gray-300 text-xl">⭐</span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-gray-900 truncate">{{ rating.user?.name || 'Anonymous' }}</p>
                  <p class="text-xs text-gray-500 mb-1">{{ rating.created_at }}</p>
                </div>
              </div>
            </div>
            
            <div v-else class="text-center py-12 text-gray-500">
              <p>No ratings yet. Be the first!</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { Link, router } from '@inertiajs/vue3';
import { ref } from 'vue';

defineProps({
  movie: Object,
  avgRating: Number
});

const wishlistMovies = ref([]); // para toggle rápido

const rentMovie = (movieId) => {
  router.post(`/movies/${movieId}/rent`);
};

const toggleWishlist = (movieId) => {
  router.post(`/wishlist/${movieId}/toggle`);
};

const isInWishlist = (movieId) => {
  return wishlistMovies.value.includes(movieId);
};
</script>
