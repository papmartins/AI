@props(['align' => 'right'])

@php
    use App\Helpers\LanguageHelper;
    
    $currentLang = LanguageHelper::getCurrentLanguage();
    $availableLanguages = LanguageHelper::getAvailableLanguages();
@endphp

<div class="relative inline-block text-left">
    <!-- Language Switcher Button -->
    <div>
        <button type="button" class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800" 
                id="language-menu-button" aria-expanded="false" aria-haspopup="true">
            <span>{!! LanguageHelper::getLanguageFlag($currentLang) !!}</span>
            <span class="hidden md:inline">{{ LanguageHelper::getLanguageName($currentLang) }}</span>
            <svg class="h-4 w-4 text-gray-500 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>
    </div>

    <!-- Language Dropdown Menu -->
    <div class="hidden absolute mt-2 w-40 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 dark:ring-gray-700 {{ $align === 'right' ? 'right-0' : 'left-0' }} z-50" 
         id="language-menu">
        <div class="py-1" role="menu" aria-orientation="vertical" aria-labelledby="language-menu-button">
            @foreach($availableLanguages as $lang => $languageName)
                <a href="?language={{ $lang }}" 
                   class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100 {{ $currentLang === $lang ? 'bg-gray-100 dark:bg-gray-700' : '' }}" 
                   role="menuitem">
                    <span class="mr-2">{!! LanguageHelper::getLanguageFlag($lang) !!}</span>
                    <span>{{ $languageName }}</span>
                </a>
            @endforeach
        </div>
    </div>
</div>

<script>
    // Simple dropdown toggle for language switcher
    document.addEventListener('DOMContentLoaded', function() {
        const button = document.getElementById('language-menu-button');
        const menu = document.getElementById('language-menu');
        
        if (button && menu) {
            button.addEventListener('click', function() {
                menu.classList.toggle('hidden');
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!button.contains(event.target) && !menu.contains(event.target)) {
                    menu.classList.add('hidden');
                }
            });
        }
    });
</script>