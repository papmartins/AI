<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { ref, computed, onMounted, onUnmounted } from 'vue';

const showingLanguageMenu = ref(false);
const languageMenuRef = ref(null);

const toggleLanguageMenu = () => {
    showingLanguageMenu.value = !showingLanguageMenu.value;
};

const closeLanguageMenu = () => {
    showingLanguageMenu.value = false;
};

// Click away handler
const handleClickOutside = (event) => {
    if (languageMenuRef.value && !languageMenuRef.value.contains(event.target)) {
        closeLanguageMenu();
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

// Get current language from path prefix or default to 'en'
const getCurrentLanguage = () => {
    try {
        const path = window.location.pathname;
        
        // Check if path starts with language prefix
        if (path.startsWith('/pt/')) return 'pt';
        if (path.startsWith('/es/')) return 'es';
        if (path.startsWith('/en/')) return 'en';
        
        // Check URL for language parameter (fallback)
        const urlParams = new URLSearchParams(window.location.search);
        const urlLang = urlParams.get('language');
        if (urlLang && ['en', 'pt', 'es'].includes(urlLang)) {
            return urlLang;
        }
        
        return 'en'; // Default language
    } catch (error) {
        console.error('Error getting current language:', error);
        return 'en'; // Fallback to default
    }
};

const currentLang = getCurrentLanguage();
const page = usePage();

// Available languages with proper Unicode flag emojis
const languages = {
    en: { name: 'English', flag: '🇬🇧' },  // UK flag
    pt: { name: 'Português', flag: '🇵🇹' },  // Portugal flag
    es: { name: 'Español', flag: '🇪🇸' }   // Spain flag
};

// Method to change language using path-based URLs
const changeLanguage = (code) => {
    try {
        const currentPath = window.location.pathname;
        // Remove any existing language prefix
        const pathWithoutLang = currentPath.startsWith('/en/') || currentPath.startsWith('/pt/') || currentPath.startsWith('/es/')
            ? currentPath.substring(3)
            : currentPath;
        
        // Navigate to language-prefixed URL
        if (code === 'en') {
            // For English (default), remove language prefix
            window.location.href = pathWithoutLang;
        } else {
            // For other languages, add prefix
            window.location.href = `/${code}${pathWithoutLang}`;
        }
    } catch (error) {
        console.error('Language change error:', error);
        // Fallback to query parameter
        window.location.href = `?language=${code}`;
    }
};

// Get current language details safely
const currentLanguageDetails = computed(() => {
    return languages[currentLang] || { name: currentLang, flag: '🌐' };
});

// Use the global trans function directly
const translate = (key) => {
    try {
        if (typeof window !== 'undefined' && typeof window.trans === 'function') {
            return window.trans(key);
        }
        if (typeof trans === 'function') {
            return trans(key);
        }
        return key; // Fallback to key if no translation function available
    } catch (error) {
        console.error('Translation error:', error);
        return key;
    }
};
</script>

<template>
    <div class="relative" ref="languageMenuRef">
        <!-- Language Switcher Button -->
        <button @click.stop="toggleLanguageMenu"
                class="inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-gray-500 hover:text-gray-700 hover:border-gray-300 focus:outline-none focus:text-gray-700 focus:border-gray-300 transition duration-150 ease-in-out">
            <span class="font-serif">{{ currentLanguageDetails.flag || '🌐' }}</span>
            <svg class="ml-1 h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>

        <!-- Language Dropdown Menu -->
        <div v-show="showingLanguageMenu"
             class="absolute z-50 mt-2 w-40 rounded-md shadow-lg bg-white border border-gray-200 right-0">
            <div class="py-1" role="menu" aria-orientation="vertical" aria-labelledby="options-menu">
                <button v-for="(lang, code) in languages"
                        :key="code"
                        @click="changeLanguage(code)"
                        class="w-full text-left block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900"
                        :class="{ 'text-gray-900 bg-gray-100': currentLang === code }"
                        role="menuitem">
                    <div class="flex items-center justify-center">
                        <span class="font-serif">{{ lang.flag || '🌐' }}</span>
                    </div>
                </button>
            </div>
        </div>
    </div>
</template>

