<template>
  <Head title="Detalhes do Filme" />
  <AuthenticatedLayout title="Detalhes do Filme">
    <div class="min-h-screen bg-gradient-to-br from-gray-50 to-gray-100 py-12">
    <!-- Back Button -->
    <div class="max-w-4xl mx-auto px-6">
      <button 
        @click="goBack"
        class="inline-flex items-center gap-2 text-indigo-600 hover:text-indigo-800 font-medium mb-12 transition-colors"
      >
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Back
      </button>
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
                  <span v-if="movie.age_rating" class="px-4 py-2 bg-purple-100 text-purple-800 rounded-full font-semibold">
                    {{ movie.age_rating + '+' }}
                  </span>
                  <div class="flex items-center gap-2">
                    <span class="text-2xl text-yellow-500"><IconStar size="md" /></span>
                    <span class="font-bold text-gray-500">{{ formattedAvgRating }}</span>
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

              <!-- Rent / Wishlist Buttons -->
              <div class="flex gap-4">
                <div class="flex-1">
                  <button
                    v-if="!localIsRented"
                    @click.prevent="rentMovie(movie.id)"
                    class="w-full bg-gradient-to-r from-green-600 to-emerald-600 text-white py-4 px-8 rounded-xl font-semibold hover:shadow-lg"
                  >
                    🎬 Rent Now - ${{ movie.price }}
                  </button>
                  <div v-else class="w-full text-center py-4 px-8 rounded-xl bg-gray-100 text-gray-600 font-semibold">
                    ✅ Already Rented
                  </div>
                </div>

                <button
                  v-if="!localIsRented"
                  @click="toggleWishlist(movie.id)"
                  :class="localIsInWishlist ? 'px-8 py-4 bg-red-50 text-red-600 border border-red-200 rounded-xl font-semibold' : 'px-8 py-4 border-2 border-indigo-300 rounded-xl text-indigo-600 font-semibold hover:bg-indigo-50'"
                >
                  {{ localIsInWishlist ? '❤️ Remove' : '💖 Wishlist' }}
                </button>
              </div>
            </div>
          </div>

          <!-- Right: Ratings -->
          <div class="md:w-1/3 bg-gradient-to-b from-indigo-50 to-blue-50 p-12">
            <h3 class="text-2xl font-bold text-gray-900 mb-8">Ratings ({{ localRatings.length }})</h3>
            
            <div v-if="localRatings.length" class="space-y-4 max-h-48 overflow-y-auto">
              <div 
                v-for="rating in localRatings.slice(0, 8)" 
                :key="rating.id" 
                class="flex items-start gap-4 p-4 bg-white rounded-lg shadow-sm hover:shadow-md transition-shadow"
              >
                <div class="flex items-center gap-1 flex-shrink-0">
                  <span v-for="n in 5" :key="n" :class="[n <= getRatingStars(rating.rating) ? 'text-yellow-400' : 'text-gray-300', 'text-xl']"><IconStar size="md" /></span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="font-semibold text-gray-900 truncate">{{ rating.user?.name || 'Anonymous' }}</p>
                  <p class="text-xs text-gray-500 mb-1">{{ formatDate(rating.created_at || rating.updated_at) }}</p>
                  <p v-if="currentUserId && rating.user_id === currentUserId" class="text-xs text-indigo-600">Your rating</p>
                </div>
              </div>
            </div>
            
            <div v-else class="text-center py-12 text-gray-500">
              <p>No ratings yet. Be the first!</p>
            </div>

            <!-- Rate Movie Form -->
            <div v-if="!localUserRating" class="mt-8 pt-8 border-t">
              <h3 class="text-xl font-bold text-gray-900 mb-4">Your Rating</h3>
              <p class="text-gray-600 mb-4">Rate this movie:</p>
              <div class="flex gap-2 justify-center">
                <button
                  v-for="n in 5"
                  :key="n"
                  @click="submitRating(movie.id, n)"
                  class="text-4xl hover:scale-110 transition-transform"
                  :class="selectedRating >= n ? 'text-yellow-400' : 'text-gray-300'"
                >
                  <IconStar size="md" />
                </button>
              </div>
            </div>

            <!-- Your Rating Display & Edit -->
            <div v-else class="mt-8 pt-8 border-t">
              <h3 class="text-xl font-bold text-gray-900 mb-4">Your Rating</h3>
              <div v-if="!isEditing" class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                  <div class="flex">
                    <span v-for="n in 5" :key="n" :class="[ n <= getRatingStars(localUserRating) ? 'text-yellow-400' : 'text-gray-300', 'text-2xl' ]">
                      <IconStar size="md" />
                    </span>
                  </div>
                  <span class="text-lg font-semibold text-gray-800">{{ localUserRating }}/5</span>
                </div>
              </div>
              <div v-else class="flex gap-2 justify-center mb-4">
                <button
                  v-for="n in 5"
                  :key="n"
                  @click="submitRating(movie.id, n)"
                  class="text-4xl hover:scale-110 transition-transform"
                  :class="selectedRating >= n ? 'text-yellow-400' : 'text-gray-300'"
                >
                  <IconStar size="md" />
                </button>
              </div>
              <div class="flex gap-3 mt-3">
                <button @click="isEditing = !isEditing" class="text-sm text-indigo-600 hover:underline">
                  {{ isEditing ? 'Cancel' : 'Change rating' }}
                </button>
                <button @click="deleteRating(userRatingId)" class="text-sm text-red-600 hover:underline">Delete</button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- You Might Also Like Section -->
    <div v-if="similarMovies.length > 0" class="max-w-6xl mx-auto px-6 mt-12">
      <div class="bg-white rounded-2xl shadow-xl overflow-hidden">
        <div class="p-8">
          <h2 class="text-3xl font-bold text-gray-900 mb-8">🎬 You Might Also Like</h2>
          <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
            <MovieCard
              v-for="similarMovie in similarMovies"
              :key="similarMovie.id"
              :movie="similarMovie"
              :rented-movie-ids="[]"
              :show-score="true"
              :score="Number(similarMovie.ratings_avg_rating)?.toFixed(1) || 0"
            />
          </div>
        </div>
      </div>
    </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Link, Head, usePage } from '@inertiajs/vue3';
import { Inertia } from '@inertiajs/inertia';
import axios from 'axios';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ref, computed } from 'vue';
import IconStar from '@/Components/Icons/IconStar.vue';
import MovieCard from '@/Components/MovieCard.vue';

const page = usePage();
const currentUserId = computed(() => page.props.auth?.user?.id);

const formattedAvgRating = computed(() => {
  return Number(props.avgRating)?.toFixed(1) || 'N/A';
});

const props = defineProps({
  movie: Object,
  avgRating: Number,
  isInWishlist: Boolean,
  isRented: Boolean,
  userRating: Number,
  similarMovies: { type: Array, default: () => [] },
});

// local reactive state so buttons update immediately
const localIsRented = ref(props.isRented);
const localIsInWishlist = ref(props.isInWishlist);
const localUserRating = ref(props.userRating || null);
const selectedRating = ref(props.userRating || 0);
const localRatings = ref([...props.movie.ratings]);

// find user's rating id if they already rated
const getUserRatingId = () => {
  const userRating = localRatings.value.find(r => r.user_id === currentUserId.value);
  return userRating?.id || null;
};

let userRatingId = getUserRatingId();

// small helper to format dates as YYYY-MM-DD HH:MM:SS
const pad = (n) => String(n).padStart(2, '0');
const formatDate = (iso) => {
  if (!iso) return '';
  const d = new Date(iso);
  if (isNaN(d.getTime())) return '';
  const Y = d.getFullYear();
  const M = pad(d.getMonth() + 1);
  const D = pad(d.getDate());
  const h = pad(d.getHours());
  const m = pad(d.getMinutes());
  const s = pad(d.getSeconds());
  return `${Y}-${M}-${D} ${h}:${m}:${s}`;
};

// editing state for user's own rating
const isEditing = ref(false);

// ensure rating value is a number
const getRatingStars = (ratingValue) => {
  return Math.max(0, Math.min(5, parseInt(ratingValue) || 0));
};

const submitRating = async (movieId, rating) => {
  try {
    const res = await axios.post(`/movies/${movieId}/rate`, { rating });
    alert(res.data?.message || 'Rating saved');
    localUserRating.value = rating;
    selectedRating.value = rating;
    
    // update local ratings list
    const ratingData = res.data?.rating;
    if (ratingData) {
      const existingIdx = localRatings.value.findIndex(r => r.user_id === ratingData.user_id);
      if (existingIdx !== -1) {
        localRatings.value[existingIdx] = ratingData;
      } else {
        localRatings.value.push(ratingData);
      }
      userRatingId = ratingData.id;
    }
    Inertia.reload();
  } catch (err) {
    alert(err.response?.data?.message || 'Error saving rating');
  }
};

const deleteRating = async (ratingId) => {
  if (!confirm('Delete your rating?')) return;
  try {
    await axios.delete(`/ratings/${ratingId}`);
    alert('Rating deleted');
    localUserRating.value = null;
    selectedRating.value = 0;
    localRatings.value = localRatings.value.filter(r => r.id !== ratingId);
    userRatingId = null;
    Inertia.reload();
  } catch (err) {
    alert(err.response?.data?.message || 'Error deleting rating');
  }
};

const rentMovie = async (movieId) => {
  try {
    const res = await axios.post(`/movies/${movieId}/rent`);
    alert(res.data?.message || 'Movie rented');
    // update local state immediately to reflect button change
    localIsRented.value = true;
    Inertia.reload();
  } catch (err) {
    alert(err.response?.data?.message || 'Error renting movie');
  }
};

const toggleWishlist = async (movieId) => {
  try {
    const res = await axios.post(`/wishlist/${movieId}/toggle`);
    alert(res.data?.message || 'Wishlist updated');
    // toggle local state immediately
    localIsInWishlist.value = !localIsInWishlist.value;
    Inertia.reload();
  } catch (err) {
    alert(err.response?.data?.message || 'Error updating wishlist');
  }
};

const goBack = () => {
  if (typeof window !== 'undefined' && window.history) {
    window.history.back();
  }
};
</script>
