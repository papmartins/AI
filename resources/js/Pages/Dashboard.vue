<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout title="Dashboard">

        <div class="py-12">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
                <!-- Recommended Movies Section -->
                <div v-if="recommendations.length > 0" class="mb-12">
                    <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                        <div class="p-6 bg-white border-b border-gray-200">
                            <div class="text-gray-900 text-lg font-semibold">🎬 Recommended for You</div>
                            <p v-if="usingMLRecommendations" class="text-sm text-gray-500 mt-1">
                                Personalized recommendations based on your preferences
                            </p>
                            <p v-else class="text-sm text-gray-500 mt-1">
                                Popular movies you might enjoy
                            </p>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                                <MovieCard
                                    v-for="movie in recommendations"
                                    :key="movie.id"
                                    :movie="movie"
                                    :rented-movie-ids="[]"
                                    :show-score="true"
                                    :score="Number(movie.ratings_avg_rating)?.toFixed(1)"
                                />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Welcome Section -->
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 bg-white border-b border-gray-200">
                        <div class="text-gray-900 text-lg font-semibold">Bem-vindo!</div>
                    </div>
                    <div class="p-6 text-gray-600">
                        <p>Está logado com sucesso. Comece a explorar:</p>
                        <ul class="mt-4 space-y-2">
                            <li><a href="/movies" class="text-blue-600 hover:underline">🎬 Ver Filmes</a></li>
                            <li><a href="/rentals" class="text-blue-600 hover:underline">🎞️ Meus Aluguéis</a></li>
                            <li><a href="/wishlist" class="text-blue-600 hover:underline">❤️ Minha Lista de Desejos</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>

