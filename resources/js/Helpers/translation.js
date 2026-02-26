/**
 * Translation helper function for Vue components
 * @param {string} key - The translation key
 * @param {Object} replace - Replacement values
 * @returns {string} - Translated string
 */
import { usePage } from '@inertiajs/vue3';
export function trans(key, replace = {}) {
    try {
        // Get the translations from the page props
        // Try multiple ways to access the page object
        const page = usePage();
        
        // Get translations - handle both modern and legacy Inertia structures
        const translations = page?.props?.translations || 
                             (page?.props?.value?.translations) || {};
        // Flatten all translations into a single object
        const allTranslations = {};
        for (const [group, groupTranslations] of Object.entries(translations)) {
            if (typeof groupTranslations === 'object' && groupTranslations !== null) {
                for (const [k, v] of Object.entries(groupTranslations)) {
                    allTranslations[k] = v;
                }
            }
        }
        
        // Get the translation
        let translation = allTranslations[key] || key;
        
        // Replace placeholders
        if (typeof replace === 'object' && replace !== null) {
            for (const [placeholder, value] of Object.entries(replace)) {
                translation = translation.replace(`:${placeholder}`, value);
            }
        }
        
        return translation;
    } catch (error) {
        console.error('Translation error:', error);
        return key; // Return the key as fallback
    }
}