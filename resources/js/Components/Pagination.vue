<template>
  <div v-if="links.length > 3" class="flex items-center gap-2">
    <Link 
      v-for="link in links" 
      :key="link.url"
      :href="link.url" 
      class="px-4 py-2 rounded-lg text-sm font-medium transition-colors"
      :class="{
        'bg-indigo-600 text-white': link.active,
        'bg-gray-200 hover:bg-gray-300': !link.active
      }"
      v-html="link.label"
    />
  </div>
</template>

<script setup>
import { Link } from '@inertiajs/vue3';
defineProps({ links: Array });
</script>
