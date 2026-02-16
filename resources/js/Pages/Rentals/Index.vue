<template>
  <div class="max-w-7xl mx-auto p-6">
    <h1 class="text-3xl font-bold mb-8">My Rentals</h1>
    
    <div v-if="rentals.data.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="rental in rentals.data" :key="rental.id" class="bg-white border rounded-lg shadow-md p-6">
        <h3 class="text-xl font-bold mb-2">{{ rental.movie.title }}</h3>
        <p class="text-gray-600 mb-4">{{ rental.movie.genre.name }} • {{ rental.movie.year }}</p>
        
        <div class="space-y-2 mb-6">
          <div class="flex justify-between">
            <span>Rented:</span>
            <span class="font-semibold">{{ rental.rented_at }}</span>
          </div>
          <div class="flex justify-between">
            <span>Due:</span>
            <span :class="rental.due_date < today ? 'text-red-500 font-bold' : 'text-green-600 font-semibold'">
              {{ rental.due_date }}
            </span>
          </div>
          <div class="flex justify-between">
            <span>Status:</span>
            <span :class="rental.returned ? 'text-green-600' : 'text-red-500'">
              {{ rental.returned ? 'Returned' : 'Active' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-20">
      <p class="text-xl text-gray-500 mb-4">No rentals yet.</p>
      <Link href="/movies" class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700">
        Browse Movies
      </Link>
    </div>

    <Pagination v-if="rentals.data.length" :links="rentals.links" />
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Pagination from '@/Components/Pagination.vue';

defineProps({
  rentals: Object
});

const today = computed(() => new Date().toISOString().split('T')[0]);
</script>
