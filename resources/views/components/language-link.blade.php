@props(['align' => 'right', 'style' => 'dropdown'])

@php
    use App\Helpers\LanguageHelper;
    
    $currentLang = LanguageHelper::getCurrentLanguage();
    $availableLanguages = LanguageHelper::getAvailableLanguages();
@endphp

@if($style === 'dropdown')
    <!-- Dropdown Style (similar to language-switcher) -->
    <div x-data="{ open: false }" @click.away="open = false" class="relative">
        <button @click="open = !open" class="flex items-center space-x-2 px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 dark:focus:ring-offset-gray-800">
            <span>{!! LanguageHelper::getLanguageFlag($currentLang) !!}</span>
            <span>{{ LanguageHelper::getLanguageName($currentLang) }}</span>
            <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
            </svg>
        </button>

        <div x-show="open" x-transition class="absolute mt-2 w-40 rounded-md shadow-lg bg-white dark:bg-gray-800 ring-1 ring-black ring-opacity-5 dark:ring-gray-700 {{ $align === 'right' ? 'right-0' : 'left-0' }} z-50">
            <div class="py-1">
                @foreach($availableLanguages as $lang => $languageName)
                    <a href="?language={{ $lang }}" 
                       class="flex items-center px-4 py-2 text-sm text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 hover:text-gray-900 dark:hover:text-gray-100 {{ $currentLang === $lang ? 'bg-gray-100 dark:bg-gray-700' : '' }}">
                        <span class="mr-2">{!! LanguageHelper::getLanguageFlag($lang) !!}</span>
                        <span>{{ $languageName }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@elseif($style === 'inline')
    <!-- Inline Style (simple links) -->
    <div class="flex items-center space-x-2">
        @foreach($availableLanguages as $lang => $languageName)
            <a href="?language={{ $lang }}" 
               class="flex items-center space-x-1 px-2 py-1 text-xs font-medium {{ $currentLang === $lang ? 'text-blue-600 dark:text-blue-400 border-b-2 border-blue-500' : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300' }}">
                <span>{!! LanguageHelper::getLanguageFlag($lang) !!}</span>
                @if($currentLang === $lang)
                    <span>{{ Str::limit($languageName, 3, '') }}</span>
                @endif
            </a>
        @endforeach
    </div>
@else
    <!-- Simple Link Style -->
    <div class="flex items-center space-x-4">
        @foreach($availableLanguages as $lang => $languageName)
            <a href="?language={{ $lang }}" 
               class="text-sm font-medium {{ $currentLang === $lang ? 'text-blue-600 dark:text-blue-400 font-semibold' : 'text-gray-600 dark:text-gray-300 hover:text-gray-900 dark:hover:text-gray-100' }}">
                {{ $languageName }}
            </a>
        @endforeach
    </div>
@endif