"""Language Detection Service - Independent language detection for the entire microservice"""

import logging
import os
import json
from typing import Dict, List

logger = logging.getLogger(__name__)


class LanguageService:
    """Centralized language detection and management"""
    
    def __init__(self):
        """Initialize language service and load all language data"""
        self.language_data = self._load_all_language_data()
        logger.info(f"Loaded language data for: {list(self.language_data.keys())}")
    
    def _load_all_language_data(self) -> Dict[str, Dict]:
        """Load language data from all JSON files"""
        language_data = {}
        base_path = os.path.join(os.path.dirname(__file__), '..', 'lang')
        
        try:
            for filename in os.listdir(base_path):
                if filename.endswith('.json'):
                    lang = filename[:-5]  # Remove .json extension
                    path = os.path.join(base_path, filename)
                    
                    try:
                        with open(path, 'r', encoding='utf-8') as f:
                            data = json.load(f)
                            language_data[lang] = data
                            logger.info(f"Loaded language data for {lang}")
                    except Exception as e:
                        logger.error(f"Failed to load language data for {lang}: {e}")
        except Exception as e:
            logger.error(f"Failed to read language directory: {e}")
        
        return language_data
    
    def detect_language(self, text: str) -> str:
        """Detect language from text using all available language indicators"""
        if not text or not text.strip():
            return 'en'
        
        lower_text = text.lower()
        logger.debug(f"Detecting language for: {text}")
        
        # Collect all indicators and confirm words from all languages
        language_scores = {}
        
        for lang, data in self.language_data.items():
            indicators = data.get('indicators', [])
            confirm_words = data.get('confirm', [])
            
            # Count how many indicators are found
            found_indicators = [word for word in indicators if word in lower_text]
            found_confirm = [word for word in confirm_words if word in lower_text]
            
            # Calculate score with better weighting
            # Only count words with 3+ characters to avoid false positives from short words
            significant_indicators = [w for w in found_indicators if len(w) >= 3]
            significant_confirm = [w for w in found_confirm if len(w) >= 3]
            
            # Give more weight to longer, more specific words
            indicator_score = sum(1 + min(len(w) - 3, 3) for w in significant_indicators)
            confirm_score = sum(2 + min(len(w) - 3, 4) for w in significant_confirm)
            score = indicator_score + confirm_score
            
            if score > 0:
                language_scores[lang] = score
                logger.debug(f"Language {lang}: indicators={significant_indicators}, confirm={significant_confirm}, score={score}")
        
        # If we found matching languages, return the one with highest score
        if language_scores:
            # Find the language with the highest score
            best_lang = max(language_scores.items(), key=lambda x: x[1])
            best_lang_name = best_lang[0]
            best_score = best_lang[1]
            
            # In case of tie, use a simple preference order: pt, es, en
            # This is just to break ties consistently, not to prefer any language
            if best_score == 2 and 'pt' in language_scores and language_scores['pt'] == 2:
                best_lang_name = 'pt'
            elif best_score == 2 and 'es' in language_scores and language_scores['es'] == 2:
                best_lang_name = 'es'
            
            logger.info(f"Detected {best_lang_name} (score: {best_score})")
            return best_lang_name
        
        # Fallback: check for specific keywords
        if any(word in lower_text for word in ['que', 'filmes', 'realizador']):
            return 'pt'
        elif any(word in lower_text for word in ['qué', 'películas', 'director']):
            return 'es'
        
        logger.info("No language detected, defaulting to English")
        return 'en'
    
    def get_language_data(self, language: str) -> Dict:
        """Get all data for a specific language"""
        return self.language_data.get(language, {})
    
    def get_available_languages(self) -> List[str]:
        """Get list of available languages"""
        return list(self.language_data.keys())


# Global instance for easy access
language_service = LanguageService()