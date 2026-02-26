<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\App;

class LanguageHelper
{
    /**
     * Get available languages
     *
     * @return array
     */
    public static function getAvailableLanguages(): array
    {
        return [
            'en' => 'English',
            'pt' => 'Português',
            'es' => 'Español',
        ];
    }
    
    /**
     * Get current language
     *
     * @return string
     */
    public static function getCurrentLanguage(): string
    {
        return Session::get('locale', App::getLocale());
    }
    
    /**
     * Get language flag icon
     *
     * @param string $lang
     * @return string
     */
    public static function getLanguageFlag(string $lang): string
    {
        $flags = [
            'en' => '🇬🇧',
            'pt' => '🇵🇹',
            'es' => '🇪🇸',
        ];
        
        return $flags[$lang] ?? '🌐';
    }
    
    /**
     * Get language name in current language
     *
     * @param string $lang
     * @return string
     */
    public static function getLanguageName(string $lang): string
    {
        $names = [
            'en' => [
                'en' => 'English',
                'pt' => 'Inglês',
                'es' => 'Inglés',
            ],
            'pt' => [
                'en' => 'Portuguese',
                'pt' => 'Português',
                'es' => 'Portugués',
            ],
            'es' => [
                'en' => 'Spanish',
                'pt' => 'Espanhol',
                'es' => 'Español',
            ],
        ];
        
        $currentLang = self::getCurrentLanguage();
        return $names[$lang][$currentLang] ?? $lang;
    }
}