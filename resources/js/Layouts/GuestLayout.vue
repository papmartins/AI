<script setup>
import ApplicationLogo from '@/Components/ApplicationLogo.vue';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-gray-100">
        <div>
            <Link href="/">
                <ApplicationLogo class="w-20 h-20 fill-current text-gray-500" />
            </Link>
        </div>
        <nav class="mt-4">
            <div class="space-x-4 text-sm text-gray-700">
                <Link :href="route('movies.index', { locale: $page.props.locale || 'en' })" class="hover:underline">Filmes</Link>
                <Link :href="route('wishlist.index', { locale: $page.props.locale || 'en' })" class="hover:underline">Wishlist</Link>
                <Link :href="route('rentals.index', { locale: $page.props.locale || 'en' })" class="hover:underline">Aluguéis</Link>
            </div>
        </nav>

        <div
            class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg"
        >
            <slot />
        </div>
    </div>
</template>
