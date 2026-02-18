<template>
  <Head title="Movies" />
  <AuthenticatedLayout title="Movies">
    <div class="max-w-7xl mx-auto p-6">
      <!-- Suggestions Section -->
      <div v-if="suggestions.length > 0" class="mb-12">
        <h2 class="text-2xl font-bold text-gray-800 mb-6">🎬 Recommended for You</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
          <MovieCard 
            v-for="movie in suggestions" 
            :key="movie.id"
            :movie="movie"
            :rented-movie-ids="props.rentedMovieIds"
            :show-score="true"
            :score="movie.ratings_avg_rating"
          />
        </div>
      </div>

      <!-- Filters -->
      <div class="mb-8 flex flex-wrap gap-4">
        <input 
          v-model="search"
          @input="fetchMovies"
          placeholder="Search movies..."
        >
        
        <select v-model="genre" class="px-4 py-2 border rounded-lg" @change="fetchMovies">
          <option value="">All Genres</option>
          <option v-for="genre in genres" :key="genre.id" :value="genre.id">{{ genre.name }}</option>
        </select>
      </div>

      <!-- Movies Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
        <MovieCard 
          v-for="movie in movies.data" 
          :key="movie.id"
          :movie="movie"
          :rented-movie-ids="props.rentedMovieIds"
          :show-score="true"
          :score="Number(movie.avg_rating)?.toFixed(1)"
        />
      </div>
      <!-- Pagination -->
      <div class="mt-12 flex justify-center">
        <Pagination :links="movies.links" />
      </div>
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, computed } from 'vue';
import Pagination from '@/Components/Pagination.vue';
import MovieCard from '@/Components/MovieCard.vue';

import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
  movies: Object,
  genres: Array,
  filters: Object,
  rentedMovieIds: { type: Array, default: () => [] },
  suggestions: { type: Array, default: () => [] },
});

const search = ref(props.filters.search || '');
const genre = ref(props.filters.genre || '');
const page = usePage();

// debounce simples sem lodash
let timeout = null;
const fetchMovies = () => {
  clearTimeout(timeout);
  timeout = setTimeout(() => {
    router.get(
      '/movies',
      { search: search.value, genre: genre.value },
      { preserveState: true, replace: true }
    );
  }, 300);
};
</script>
