<template>
  <Head title="Aluguéis" />
  <AuthenticatedLayout title="My Rents">

    <div class="max-w-7xl mx-auto p-6">      
      <div v-if="localRentals.length" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
      <div v-for="rental in localRentals" :key="rental.id" class="bg-white border rounded-lg shadow-md p-6">
        <h3 class="text-xl font-bold mb-2">{{ rental.movie.title }}</h3>
        <p class="text-gray-600 mb-4">{{ rental.movie.genre.name }} • {{ rental.movie.year }}
        <span v-if="rental.movie.age_rating" class="ml-2 px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs font-semibold">
          {{ rental.movie.age_rating  + '+'}}
        </span></p>
        
        <div class="space-y-2 mb-6">
          <div class="flex justify-between">
            <span>{{ trans('Rented') }}:</span>
            <span class="font-semibold">{{ rental.rented_at }}</span>
          </div>
          <div class="flex justify-between">
            <span>{{ trans('Due') }}:</span>
            <span :class="rental.due_date < today ? 'text-red-500 font-bold' : 'text-green-600 font-semibold'">
              {{ rental.due_date }}
            </span>
          </div>
          <div class="flex justify-between">
            <span>{{ trans('Status') }}:</span>
            <span :class="rental.returned ? 'text-green-600' : 'text-red-500'">
              {{ rental.returned ? trans('Returned') : trans('Active') }}
            </span>
          </div>
        </div>
        <div class="flex justify-end">
          <button
            v-if="!rental.returned"
            @click.prevent="returnRental(rental.id)"
            class="bg-red-500 text-white px-4 py-2 rounded-lg font-semibold hover:bg-red-600"
          >
            Return
          </button>
        </div>
      </div>
    </div>

    <div v-else class="text-center py-20">
      <p class="text-xl text-gray-500 mb-4">No rentals yet.</p>
      <Link href="/movies" class="bg-indigo-600 text-white px-6 py-3 rounded-lg font-semibold hover:bg-indigo-700">
        Browse Movies
      </Link>

      </div>

      <Pagination v-if="localRentals.length" :links="rentals.links" />
    </div>
  </AuthenticatedLayout>
</template>

<script setup>
import { computed, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Inertia } from '@inertiajs/inertia';
import axios from 'axios';
import Pagination from '@/Components/Pagination.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head } from '@inertiajs/vue3';

const props = defineProps({
  rentals: Object
});
const rentals = props.rentals;

const today = computed(() => new Date().toISOString().split('T')[0]);

// Local reactive copy so we can update UI without reloading
const localRentals = ref([...rentals.data]);

const returnRental = async (id) => {
  if (!confirm('Confirm return?')) return;

  try {
    const res = await axios.delete(`/rentals/${id}`);
    const msg = res.data?.message || 'Rental returned successfully.';
    // mark returned in local list
    localRentals.value = localRentals.value.map((r) => (r.id === id ? { ...r, returned: true } : r));
    alert(msg);
  } catch (err) {
    const errMsg = err.response?.data?.message || 'Error returning rental.';
    alert(errMsg);
  }
};
</script>
