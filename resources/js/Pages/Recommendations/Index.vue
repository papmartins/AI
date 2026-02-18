<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
import MovieCard from '@/Components/MovieCard.vue';

const props = defineProps({
  personalizedRecommendations: { type: Array, default: () => [] },
  popularRecommendations: { type: Array, default: () => [] },
});
</script>

<template>
    <Head title="Recommendations" />

    <AuthenticatedLayout title="Recommendations">
        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Personalized Recommendations Section -->
                <div v-if="personalizedRecommendations.length > 0" class="mb-12">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 bg-white border-b border-gray-200">
                            <h2 class="text-2xl font-bold text-gray-900">🎬 Personalized for You</h2>
                            <p class="text-sm text-gray-500 mt-1">
                                Based on your ratings, rentals, and preferences
                            </p>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                <MovieCard
                                    v-for="rec in personalizedRecommendations"
                                    :key="rec.movie.id"
                                    :movie="rec.movie"
                                    :rented-movie-ids="[]"
                                    :show-score="true"
                                    :score="Number(rec.movie?.ratings_avg_rating)?.toFixed(1)"
                                    :confidence="rec.confidence"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Popular Recommendations Section -->
                <div v-if="popularRecommendations.length > 0" class="mb-12">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 bg-white border-b border-gray-200">
                            <h2 class="text-2xl font-bold text-gray-900">🔥 Popular Picks</h2>
                            <p class="text-sm text-gray-500 mt-1">
                                Most popular movies across all users
                            </p>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                                <MovieCard
                                    v-for="rec in popularRecommendations"
                                    :key="rec.movie.id"
                                    :movie="rec.movie"
                                    :rented-movie-ids="[]"
                                    :show-score="true"
                                    :score="Number(rec.predicted_rating)?.toFixed(1)"
                                    :confidence="rec.confidence"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- No Recommendations Fallback -->
                <div v-if="personalizedRecommendations.length === 0 && popularRecommendations.length === 0">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 text-center">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">No recommendations available</h3>
                            <p class="text-gray-600 mb-4">
                                Start rating and renting movies to get personalized recommendations!
                            </p>
                            <a
                                href="/movies"
                                class="inline-flex items-center px-4 py-2 bg-indigo-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-indigo-700 active:bg-indigo-900 focus:outline-none focus:border-indigo-900 focus:ring ring-gray-300 disabled:opacity-25 transition ease-in-out duration-150"
                            >
                                Browse Movies
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

<style scoped>
/* Add any custom styles here */
</style>