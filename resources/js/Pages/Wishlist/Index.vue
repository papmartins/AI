<template>
    <AuthenticatedLayout title="My Wishlist">
        <div class="max-w-7xl mx-auto p-6">
            <!-- Header -->
            <div class="flex justify-between items-center mb-12">
            <div>
                <p class="text-xl text-gray-600">{{ wishlistMovies.length }} movies</p>
            </div>
            <Link href="/movies" class="bg-indigo-600 text-white px-8 py-3 rounded-xl font-semibold hover:bg-indigo-700 transition-colors">
                Browse All Movies
            </Link>
            </div>

            <!-- Empty State -->
            <div v-if="!wishlistMovies.length" class="text-center py-24">
            <div class="w-32 h-32 mx-auto mb-6 bg-gray-100 rounded-3xl flex items-center justify-center">
                <svg class="w-16 h-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M3 3h18l-2 12H5L3 3zM12 15a2 2 0 100-4 2 2 0 000 4z"/>
                </svg>
            </div>
            <h3 class="text-2xl font-semibold text-gray-900 mb-4">Your wishlist is empty</h3>
            <p class="text-lg text-gray-600 mb-8">Save movies you want to rent later.</p>
            <Link href="/movies" class="bg-gradient-to-r from-indigo-600 to-purple-600 text-white px-8 py-4 rounded-xl font-semibold shadow-lg hover:shadow-xl transform hover:-translate-y-1 transition-all">
                Start Browsing
            </Link>
            </div>

            <!-- Movies Grid -->
            <div v-else class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <div 
                v-for="movie in wishlistMovies" 
                :key="movie.id"
                class="group bg-white border border-gray-200 rounded-2xl shadow-md hover:shadow-2xl hover:-translate-y-2 transition-all duration-300 overflow-hidden"
            >
                <!-- Image Placeholder -->
                <div class="h-64 bg-gradient-to-br from-gray-100 to-gray-200 flex items-center justify-center group-hover:from-indigo-50 group-hover:to-purple-50">
                <div class="text-4xl opacity-20">🎬</div>
                </div>

                <!-- Content -->
                <div class="p-6">
                <div class="flex items-start justify-between mb-3">
                    <h3 class="font-bold text-xl text-gray-900 line-clamp-1 group-hover:text-indigo-600 transition-colors">
                    {{ movie.title }}
                    </h3>
                    <span class="px-3 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                    ${{ movie.price }}
                    </span>
                </div>

                <p class="text-gray-600 text-sm mb-4 line-clamp-2">{{ movie.description }}</p>

                <div class="flex items-center justify-between mb-4">
                    <div class="flex gap-2">
                        <span class="px-3 py-1 bg-indigo-100 text-indigo-800 text-xs rounded-full">
                        {{ movie.genre.name }}
                        </span>
                        <span v-if="movie.age_rating" class="px-3 py-1 bg-purple-100 text-purple-800 text-xs rounded-full font-semibold">
                        {{ movie.age_rating + '+'}}
                        </span>
                    </div>
                    <span class="text-sm text-gray-500">{{ movie.year }}</span>
                </div>

                <div class="flex items-center gap-2 mb-6">
                    <div class="flex">
                    <span class="text-yellow-400 text-sm"><IconStar size="md" /></span>
                    </div>
                    <span class="text-sm text-gray-600">{{ Number(movie.avg_rating)?.toFixed(1) }}/5</span>
                </div>

                <!-- Actions -->
                <div class="flex gap-3 pt-4 border-t">
                    <form @submit.prevent="rentMovie(movie.id)" class="flex-1">
                    <button 
                        type="submit"
                        class="w-full bg-gradient-to-r from-emerald-500 to-green-600 text-white py-3 px-4 rounded-xl font-semibold hover:shadow-lg transition-all"
                    >
                        🎬 Rent Now
                    </button>
                    </form>
                    
                    <form @submit.prevent="removeFromWishlist(movie.id)" class="flex-shrink-0">
                    <button 
                        type="submit"
                        class="p-3 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-xl transition-all"
                        title="Remove from wishlist"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                    </form>
                </div>
                </div>
            </div>
            </div>

            <!-- Pagination -->
            <div v-if="movies.links.length" class="mt-12 flex justify-center">
            <Pagination :links="movies.links" />
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { Inertia } from '@inertiajs/inertia';
import axios from 'axios';
import { computed, ref, watch } from 'vue';
import Pagination from '@/Components/Pagination.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import IconStar from '@/Components/Icons/IconStar.vue';

const props = defineProps({
  movies: Object
});

// local reactive copy so we can update UI immediately
const localWishlist = ref([...props.movies.data]);
const wishlistMovies = computed(() => localWishlist.value);

// keep local copy in sync if props change (pagination, reload)
watch(
    () => props.movies.data,
    (val) => {
        localWishlist.value = [...val];
    }
);

const avgRating = (movie) => {
  return movie.ratings?.length ? (movie.ratings.reduce((a, b) => a + b.rating, 0) / movie.ratings.length).toFixed(1) : 'N/A';
};

const rentMovie = async (movieId) => {
    try {
        const res = await axios.post(`/movies/${movieId}/rent`);
        const msg = res.data?.message || 'Movie rented!';
        alert(msg);
        // remove from local wishlist immediately
        localWishlist.value = localWishlist.value.filter(m => m.id !== movieId);
        Inertia.reload();
    } catch (err) {
        alert(err.response?.data?.message || 'Error renting movie');
    }
};

const removeFromWishlist = async (movieId) => {
    try {
        const res = await axios.post(`/wishlist/${movieId}/toggle`);
        const msg = res.data?.message || 'Updated wishlist';
        alert(msg);
        // remove from local wishlist when removed, otherwise reload to reflect add
        localWishlist.value = localWishlist.value.filter(m => m.id !== movieId);
        Inertia.reload();
    } catch (err) {
        alert(err.response?.data?.message || 'Error updating wishlist');
    }
};
</script>
