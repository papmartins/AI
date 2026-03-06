import json
import os
import logging
from typing import Dict, List, Optional

# Configure logging for language detector
logger = logging.getLogger(__name__)

class LanguageDetector:
    """Language detection using configuration files"""
    
    def __init__(self):
        logger.info("Initializing LanguageDetector...")
        self.languages = {}
        self.load_language_configs()
    
    def load_language_configs(self):
        """Load language configuration files"""
        config_dir = os.path.join(os.path.dirname(__file__), '..', 'config', 'language')
        logger.info(f"Loading language configs from: {config_dir}")
        logger.info(f"Files in directory: {os.listdir(config_dir) if os.path.exists(config_dir) else 'Directory not found'}")
        
        try:
            if not os.path.exists(config_dir):
                logger.error(f"Config directory not found: {config_dir}")
                raise FileNotFoundError(f"Config directory not found: {config_dir}")
            
            for filename in os.listdir(config_dir):
                if filename.endswith('.json'):
                    filepath = os.path.join(config_dir, filename)
                    logger.info(f"Loading file: {filepath}")
                    with open(filepath, 'r', encoding='utf-8') as f:
                        config = json.load(f)
                        # Store the full config including keywords, movie_indicators, and genre_patterns
                        language_config = {
                            'indicators': config.get('indicators', []),
                            'common_questions': config.get('common_questions', []),
                            'keywords': config.get('keywords', {})
                        }
                        # Add movie_indicators and genre_patterns to keywords if they exist in config
                        # (they should already be in keywords from the JSON, but this ensures they're there)
                        if 'movie_indicators' in config:
                            language_config['keywords']['movie_indicators'] = config['movie_indicators']
                        if 'genre_patterns' in config:
                            language_config['keywords']['genre_patterns'] = config['genre_patterns']
                        if 'genre_question_patterns' in config:
                            language_config['keywords']['genre_question_patterns'] = config['genre_question_patterns']
                        if 'genre_of_movie_patterns' in config:
                            language_config['keywords']['genre_of_movie_patterns'] = config['genre_of_movie_patterns']
                        
                        # Log all the important configuration details for debugging
                        logger.info(f"Loaded language {config['language']}: "
                                  f"movie_indicators={language_config['keywords'].get('movie_indicators', [])}, "
                                  f"genre_patterns={language_config['keywords'].get('genre_patterns', [])}, "
                                  f"actor_title_phrases={language_config['keywords'].get('actor_title_phrases', [])}")
                        self.languages[config['language']] = language_config
        except Exception as e:
            logger.error(f"Error loading language configs: {e}")
            import traceback
            logger.error(f"Traceback: {traceback.format_exc()}")
            # Fallback to default configuration if files not found
            self.languages = {
                'pt': {
                    'indicators': ['ator', 'diretor', 'filme', 'gênero', 'avaliação'],
                    'common_questions': ['quais filmes', 'que filmes'],
                    'keywords': {
                        'director': ['diretor', 'diretora', 'realizador', 'dirigido por'],
                        'actor': ['ator', 'atriz', 'protagonizado por'],
                        'genre': ['gênero', 'ação', 'comédia', 'drama'],
                        'year': ['ano', 'lançamento'],
                        'rating': ['avaliação', 'nota'],
                        'title': ['título'],
                        'recommend': ['recomendar', 'melhor'],
                        'question_words': ['what', 'which', 'who', 'qual', 'quais', 'quem', 'que']
                    }
                },
                'en': {
                    'indicators': ['actor', 'director', 'movie', 'genre', 'rating'],
                    'common_questions': ['what movies', 'which movies'],
                    'keywords': {
                        'director': ['director', 'filmmaker', 'directed by'],
                        'actor': ['actor', 'actress', 'starring'],
                        'genre': ['genre', 'action', 'comedy', 'drama'],
                        'year': ['year', 'release'],
                        'rating': ['rating', 'score'],
                        'title': ['title'],
                        'recommend': ['recommend', 'best'],
                        'question_words': ['what', 'which', 'who']
                    }
                },
                'es': {
                    'indicators': ['actor', 'director', 'película', 'género', 'valoración'],
                    'common_questions': ['qué películas', 'cuáles películas'],
                    'keywords': {
                        'director': ['director', 'realizador', 'dirigido por'],
                        'actor': ['actor', 'actriz'],
                        'genre': ['género', 'acción', 'comedia'],
                        'year': ['año', 'estreno'],
                        'rating': ['valoración'],
                        'title': ['título'],
                        'recommend': ['recomendar', 'mejor'],
                        'question_words': ['qué', 'cuál', 'quién']
                    }
                }
            }
            
            if not self.languages:
                raise FileNotFoundError("No language configuration files found")
                
        except Exception as e:
            logger.error(f"Error loading language configs: {e}")
            import traceback
            logger.error(f"Traceback: {traceback.format_exc()}")
            # Fallback to default configuration if files not found
            self.languages = {
                'pt': {
                    'indicators': ['ator', 'diretor', 'filme', 'gênero', 'avaliação'],
                    'common_questions': ['quais filmes', 'que filmes'],
                    'keywords': {
                        'director': ['diretor', 'diretora', 'realizador', 'dirigido por'],
                        'actor': ['ator', 'atriz', 'protagonizado por'],
                        'genre': ['gênero', 'ação', 'comédia', 'drama'],
                        'year': ['ano', 'lançamento'],
                        'rating': ['avaliação', 'nota'],
                        'title': ['título'],
                        'recommend': ['recomendar', 'melhor'],
                        'question_words': ['what', 'which', 'who', 'qual', 'quais', 'quem', 'que']
                    }
                },
                'en': {
                    'indicators': ['actor', 'director', 'movie', 'genre', 'rating'],
                    'common_questions': ['what movies', 'which movies'],
                    'keywords': {
                        'director': ['director', 'filmmaker', 'directed by'],
                        'actor': ['actor', 'actress', 'starring'],
                        'genre': ['genre', 'action', 'comedy', 'drama'],
                        'year': ['year', 'release'],
                        'rating': ['rating', 'score'],
                        'title': ['title'],
                        'recommend': ['recommend', 'best'],
                        'question_words': ['what', 'which', 'who']
                    }
                },
                'es': {
                    'indicators': ['actor', 'director', 'película', 'género', 'valoración'],
                    'common_questions': ['qué películas', 'cuáles películas'],
                    'keywords': {
                        'director': ['director', 'realizador', 'dirigido por'],
                        'actor': ['actor', 'actriz'],
                        'genre': ['género', 'acción', 'comedia'],
                        'year': ['año', 'estreno'],
                        'rating': ['valoración'],
                        'title': ['título'],
                        'recommend': ['recomendar', 'mejor'],
                        'question_words': ['qué', 'cuál', 'quién']
                    }
                }
            }
    
    def detect_language(self, text: str) -> str:
        """Detect language of the input text using configuration files"""
        if not text or not isinstance(text, str):
            return 'en'
        
        text_lower = text.lower()
        results = {}
        
        # Count indicators for each language
        for lang_code, config in self.languages.items():
            indicators = config.get('indicators', [])
            count = sum(1 for indicator in indicators if indicator in text_lower)
            
            # Add bonus for language-specific words (English)
            if lang_code == 'en' and 'english_specific' in config:
                english_specific = config['english_specific']
                english_count = sum(1 for word in english_specific if word in text_lower)
                count += english_count * 2  # Give more weight to English-specific words
            
            results[lang_code] = count
        
        # Find language with highest count
        if results:
            return max(results.items(), key=lambda x: x[1])[0]
        
        return 'en'
    
    def get_language_name(self, language_code: str) -> str:
        """Get full language name"""
        return self.languages.get(language_code, {}).get('name', language_code)
    
    def get_supported_languages(self) -> List[str]:
        """Get list of supported languages"""
        return list(self.languages.keys())
    
    def check_common_question(self, text: str, language: str) -> bool:
        """Check if text matches common question patterns"""
        if language not in self.languages:
            return False
        
        text_lower = text.lower()
        common_questions = self.languages[language].get('common_questions', [])
        
        return any(question in text_lower for question in common_questions)

# Singleton instance
language_detector = LanguageDetector()

if __name__ == "__main__":
    # Test the language detector
    detector = language_detector
    
    test_cases = [
        ("Quais filmes têm o ator Tom Hanks?", "pt"),
        ("What movies star Tom Hanks?", "en"),
        ("¿Qué películas protagoniza Tom Hanks?", "es"),
        ("Hello world", "en")
    ]
    
    print("Language Detection Tests:")
    for text, expected in test_cases:
        result = detector.detect_language(text)
        status = "✓" if result == expected else "✗"
        print(f"{status} '{text}' -> {result} (expected: {expected})")

# Singleton instance - created when module is imported
language_detector = LanguageDetector()