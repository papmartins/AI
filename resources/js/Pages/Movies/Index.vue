<template>
  <div class="max-w-7xl mx-auto p-6">
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
      <div 
        v-for="movie in movies.data" 
        :key="movie.id"
        class="bg-white border rounded-lg shadow-md hover:shadow-xl transition-shadow p-6"
      >
        <h3 class="text-xl font-bold mb-2 line-clamp-2">{{ movie.title }} ({{ movie.year }})</h3>
        <p class="text-gray-600 mb-3 line-clamp-2">{{ movie.description }}</p>
        <div class="flex justify-between items-center mb-4">
          <span class="text-lg font-semibold text-green-600">${{ movie.price }}</span>
          <span class="text-sm text-gray-500">Stock: {{ movie.stock }}</span>
        </div>
        <div class="flex gap-2 mb-4">
          <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">{{ movie.genre.name }}</span>
        </div>
        <Link 
          :href="`/movies/${movie.id}`"
          class="w-full block bg-indigo-600 text-white py-2 px-4 rounded-lg text-center hover:bg-indigo-700 transition-colors"
        >
          View Details
        </Link>
      </div>
    </div>

    <!-- Pagination -->
    <div class="mt-12 flex justify-center">
      <Pagination :links="movies.links" />
    </div>
  </div>
</template>

<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';
import Pagination from '@/Components/Pagination.vue';

const props = defineProps({
  movies: Object,
  genres: Array,
  filters: Object,
});

const search = ref(props.filters.search || '');
const genre = ref(props.filters.genre || '');
const page = usePage();

// debounce simples sem lodash
let timeout = null;
const fetchMovies = () => {
  console.log(genre)
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
