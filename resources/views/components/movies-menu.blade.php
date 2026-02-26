@props(['active' => false])

@php
    $isActive = function($route) use ($active) {
        return request()->routeIs($route) || $active === $route;
    };
@endphp

<div x-data="{ open: false }" @click.away="open = false" class="relative">
    <!-- Movies Menu Button -->
    <button @click="open = !open" 
            class="flex items-center space-x-1 px-3 py-2 text-sm font-medium {{ $isActive('movies.*') ? 'text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-700' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }} rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800">
        <svg class="h-5 w-5" fill="currentColor" viewBox="0 0 20 20">
            <path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
            <path d="M18 4l-2 2h-2l-2-2-2 2H8l-2-2-2 2H2v10h16V4z"></path>
        </svg>
        <span>{{ __('messages.movies') }}</span>
        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
        </svg>
    </button>

    <!-- Movies Dropdown Menu -->
    <div x-show="open" x-transition:enter="transition ease-out duration-100" x-transition:enter-start="transform opacity-0 scale-95" x-transition:enter-end="transform opacity-100 scale-100" x-transition:leave="transition ease-in duration-75" x-transition:leave-start="transform opacity-100 scale-100" x-transition:leave-end="transform opacity-0 scale-95" 
         class="absolute mt-2 w-48 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 dark:ring-gray-700 z-50">
        <div class="py-1" role="menu" aria-orientation="vertical" aria-labelledby="options-menu">
            <a href="{{ route('movies.index') }}" 
               class="flex items-center px-4 py-2 text-sm {{ $isActive('movies.index') ? 'text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100' }}" 
               role="menuitem">
                <svg class="mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path>
                    <path d="M18 4l-2 2h-2l-2-2-2 2H8l-2-2-2 2H2v10h16V4z"></path>
                </svg>
                {{ __('messages.movies') }}
            </a>
            
            <a href="{{ route('wishlist.index') }}" 
               class="flex items-center px-4 py-2 text-sm {{ $isActive('wishlist.*') ? 'text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100' }}" 
               role="menuitem">
                <svg class="mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M3.172 5.172a4 4 0 015.656 0L10 6.343l1.172-1.171a4 4 0 115.656 5.656L10 17.657l-6.828-6.829a4 4 0 010-5.656z" clip-rule="evenodd"></path>
                </svg>
                {{ __('auth.wishlist') }}
            </a>
            
            <a href="{{ route('rentals.index') }}" 
               class="flex items-center px-4 py-2 text-sm {{ $isActive('rentals.*') ? 'text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100' }}" 
               role="menuitem">
                <svg class="mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M5 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2H5z"></path>
                    <path opacity=".3" d="M15 3a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2V5a2 2 0 00-2-2h-2z"></path>
                    <path d="M5 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2H5z"></path>
                    <path opacity=".3" d="M15 11a2 2 0 00-2 2v2a2 2 0 002 2h2a2 2 0 002-2v-2a2 2 0 00-2-2h-2z"></path>
                </svg>
                {{ __('auth.rentals') }}
            </a>
            
            <a href="{{ route('recommendations.index') }}" 
               class="flex items-center px-4 py-2 text-sm {{ $isActive('recommendations.*') ? 'text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-700' : 'text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100' }}" 
               role="menuitem">
                <svg class="mr-2 h-4 w-4" fill="currentColor" viewBox="0 0 20 20">
                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"></path>
                </svg>
                {{ __('auth.recommendations') }}
            </a>
        </div>
    </div>
</div>