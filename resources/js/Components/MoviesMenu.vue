<script setup>
import { Link } from '@inertiajs/vue3';
import { ref, onMounted, onUnmounted } from 'vue';

const showingMoviesMenu = ref(false);
const moviesMenuRef = ref(null);

const toggleMoviesMenu = () => {
    showingMoviesMenu.value = !showingMoviesMenu.value;
};

const closeMoviesMenu = () => {
    showingMoviesMenu.value = false;
};

// Click away handler
const handleClickOutside = (event) => {
    if (moviesMenuRef.value && !moviesMenuRef.value.contains(event.target)) {
        closeMoviesMenu();
    }
};

// Add event listener when component is mounted
onMounted(() => {
    document.addEventListener('click', handleClickOutside);
});

// Remove event listener when component is unmounted
onUnmounted(() => {
    document.removeEventListener('click', handleClickOutside);
});

</script>

<template>
    <div class="relative" ref="moviesMenuRef">
        <!-- Movies Menu Button -->
        <button @click.stop="toggleMoviesMenu"
                class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out h-full min-h-[40px]">
            <span>🎬 {{ trans('Movies') }}</span>
            <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>

        <!-- Movies Dropdown Menu -->
        <div v-show="showingMoviesMenu"
             class="absolute z-50 mt-2 w-48 rounded-md shadow-lg bg-white border border-gray-200">
            <div class="py-1" role="menu" aria-orientation="vertical" aria-labelledby="options-menu">
                <Link :href="route('movies.index', { locale: $page.props.locale || 'en' })"
                      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                      role="menuitem">
                    <div class="flex items-center">
                        <svg class="mr-2 h-4 w-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
                            <path d="M18 4l-2 2h-2l-2-2-2 2H8l-2-2-2 2H2v10h16V4z"></path>
                        </svg>
                        <span>{{ trans('All Movies') }}</span>
                    </div>
                </Link>

                <Link :href="route('wishlist.index', { locale: $page.props.locale || 'en' })"
                      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                      role="menuitem">
                    <div class="flex items-center">
                        <svg class="mr-2 h-4 w-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path>
                        </svg>
                        <span>{{ trans('Wishlist') }}</span>
                    </div>
                </Link>

                <Link :href="route('rentals.index', { locale: $page.props.locale || 'en' })"
                      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                      role="menuitem">
                    <div class="flex items-center">
                        <svg class="mr-2 h-4 w-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5z"></path>
                            <path opacity=".3" d="M15 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2z"></path>
                            <path d="M5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5z"></path>
                            <path opacity=".3" d="M15 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2z"></path>
                        </svg>
                        <span>{{ trans('My Rentals') }}</span>
                    </div>
                </Link>

                <Link :href="route('recommendations.show', { locale: $page.props.locale || 'en' })"
                      class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                      role="menuitem">
                    <div class="flex items-center">
                        <svg class="mr-2 h-4 w-4 text-gray-500" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                        </svg>
                        <span>{{ trans('Recommendations') }}</span>
                    </div>
                </Link>
            </div>
        </div>
    </div>
</template>