<template>
  <div class="bg-white border rounded-lg shadow-md hover:shadow-xl transition-shadow p-6">
    <div class="flex items-center justify-between mb-2">
      <h3 class="text-xl font-bold line-clamp-2">{{ movie.title }} ({{ movie.year }})</h3>
      <div class="flex items-center gap-3">
        <span v-if="rentedMovieIds.includes(movie.id)" class="text-sm text-green-600 font-semibold">Rented</span>
        <div v-if="avgRating !== null && avgRating !== undefined" class="flex items-center gap-1">
          <span class="text-yellow-500 font-bold">★</span>
          <span class="font-semibold text-sm">{{ formattedAvgRating }}</span>
        </div>
        <div v-if="showScore && score !== null" class="flex items-center gap-1">
          <span class="text-yellow-500 font-bold">★</span>
          <span class="font-semibold text-sm">{{ formattedScore }}</span>
        </div>
      </div>
    </div>
    <p class="text-gray-600 mb-3 line-clamp-2">{{ movie.description }}</p>
    <div class="flex justify-between items-center mb-4">
      <span class="text-lg font-semibold text-green-600">${{ movie.price }}</span>
      <span class="text-sm text-gray-500">Stock: {{ movie.stock }}</span>
    </div>
    <div class="flex justify-between items-center mb-4">
      <div class="flex gap-2">
        <span class="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm">{{ movie.genre.name }}</span>
      </div>
      <span v-if="movie.age_rating" class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm font-semibold">
        {{ movie.age_rating + '+' }}
      </span>
    </div>
    <div v-if="showScore && confidence !== null" class="mb-4">
      <div class="w-full bg-gray-200 rounded-full h-2">
        <div class="bg-indigo-600 h-2 rounded-full" :style="{ width: (confidence * 100) + '%' }"></div>
      </div>
      <p class="text-xs text-gray-500 mt-1 text-right">
        {{ Math.round(confidence * 100) }}% confidence
      </p>
    </div>
    <div>
      <Link
        :href="`/movies/${movie.id}`"
        class="w-full block bg-indigo-600 text-white py-2 px-4 rounded-lg text-center hover:bg-indigo-700 transition-colors"
      >
        View Details
      </Link>
    </div>
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
  movie: {
    type: Object,
    required: true
  },
  rentedMovieIds: {
    type: Array,
    default: () => []
  },
  avgRating: {
    type: [Number, String],
    default: null
  },
  showScore: {
    type: Boolean,
    default: false
  },
  score: {
    type: [Number, String],
    default: null
  },
  confidence: {
    type: Number,
    default: null
  }
});

const formattedScore = computed(() => {
  if (props.score === null) return 'N/A';
  return Number(props.score).toFixed(1);
});

const formattedAvgRating = computed(() => {
  if (props.avgRating === null) return 'N/A';
  return Number(props.avgRating).toFixed(1);
});
</script>