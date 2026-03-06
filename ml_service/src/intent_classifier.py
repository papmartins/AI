#!/usr/bin/env python3

import re
import logging
from typing import Dict, Any

logger = logging.getLogger(__name__)


def classify_intents(question: str, language: str, models: dict, languages_config: dict) -> Dict[str, Any]:
    """
    Classify intents from user question using spaCy NLP
    
    Args:
        question: User's question
        language: Language code (e.g., 'en', 'pt', 'es')
        models: Dictionary of spaCy models
        languages_config: Language configuration dictionary
        
    Returns:
        Dictionary with intents, entities, language, and confidence
    """
    try:
        logger.info(f"Classifying: {question[:50]}...")
        logger.info(f"Language: {language}")
        if language not in models:
            language = 'en'
            
        nlp = models[language]
        doc = nlp(question)

        # Initialize result
        result = {
            'intents': [],
            'entities': {},
            'language': language,
            'confidence': 0.0
        }
        
        text_lower = question.lower()
        
        # Get keywords from language config
        lang_config = languages_config.get(language, {})
        keywords = lang_config.get('keywords', {})
        
        # Check for recommendation intent
        recommend_keywords = keywords.get('recommend', [])
        recommend_phrases = keywords.get('recommend_phrases', [])
        has_recommend_keyword = any(token.text.lower() in recommend_keywords for token in doc)
        has_recommend_phrase = any(phrase in text_lower for phrase in recommend_phrases)
        
        if has_recommend_keyword or has_recommend_phrase:
            result['intents'].append('recommendation')
            result['confidence'] = 0.9
            return result
        
        # Check for "who" questions using language config
        who_question_prefixes = keywords.get('who_question_prefixes', [])
        if any(phrase in text_lower for phrase in who_question_prefixes):
            # Check for director keywords
            director_keywords = keywords.get('director', [])
            has_director_keyword = any(token.text.lower() in director_keywords for token in doc)
            
            # Check for actor keywords
            actor_keywords = keywords.get('actor', [])
            has_actor_keyword = any(token.text.lower() in actor_keywords for token in doc)
            
            # Handle combined "cast and director" queries
            if has_director_keyword and has_actor_keyword:
                # Try to extract title from director patterns first
                for pattern in keywords.get('director_title_patterns', []):
                    import re
                    match = re.search(pattern, text_lower)
                    if match:
                        full_match = match.group(0)
                        director_title_phrases = keywords.get('director_title_phrases', [])
                        for prefix in director_title_phrases:
                            if full_match.startswith(prefix):
                                title = full_match[len(prefix):].strip()
                                if title.endswith('?'):
                                    title = title[:-1].strip()
                                result['intents'].extend(['director', 'actor'])
                                result['entities']['title'] = [title.title()]
                                result['confidence'] = 0.9
                                return result
                
                # Try to extract title from actor patterns
                for pattern in keywords.get('actor_title_patterns', []):
                    import re
                    match = re.search(pattern, text_lower)
                    if match:
                        full_match = match.group(0)
                        actor_title_phrases = keywords.get('actor_title_phrases', [])
                        for prefix in actor_title_phrases:
                            if full_match.startswith(prefix):
                                title = full_match[len(prefix):].strip()
                                if title.endswith('?'):
                                    title = title[:-1].strip()
                                result['intents'].extend(['director', 'actor'])
                                result['entities']['title'] = [title.title()]
                                result['confidence'] = 0.9
                                return result
                
                # Fallback: extract title from the question
                import re
                name_pattern = r'\b([A-Z][a-z]+(?:\s[A-Z][a-z]+){1,3})\b'
                matches = re.findall(name_pattern, question)
                if matches:
                    result['intents'].extend(['director', 'actor'])
                    result['entities']['title'] = [matches[-1].strip().title()]
                    result['confidence'] = 0.8
                else:
                    result['intents'].extend(['director', 'actor'])
                    result['confidence'] = 0.7
                return result
            
            if has_director_keyword:
                # Extract title from "who directed X" patterns
                for pattern in keywords.get('director_title_patterns', []):
                    import re
                    match = re.search(pattern, text_lower)
                    if match:
                        full_match = match.group(0)
                        # Use director_title_phrases from config for prefix matching
                        director_title_phrases = keywords.get('director_title_phrases', [])
                        for prefix in director_title_phrases:
                            if full_match.startswith(prefix):
                                title = full_match[len(prefix):].strip()
                                if title.endswith('?'):
                                    title = title[:-1].strip()
                                result['intents'].append('director')
                                result['entities']['title'] = [title.title()]  # The movie title we're asking about
                                result['confidence'] = 0.8
                                return result
                
                # If no specific pattern matched, extract title and return director intent
                import re
                name_pattern = r'\b([A-Z][a-z]+(?:\s[A-Z][a-z]+){1,3})\b'
                matches = re.findall(name_pattern, question)
                if matches:
                    result['intents'].append('director')
                    result['entities']['director'] = [matches[-1].strip().title()]  # Get last name as title
                    result['confidence'] = 0.7
                else:
                    result['intents'].append('director')
                    result['confidence'] = 0.5
                return result
            
            # Check for actor keywords
            actor_keywords = keywords.get('actor', [])
            has_actor_keyword = any(token.text.lower() in actor_keywords for token in doc)
            
            if has_actor_keyword:
                # Extract title from "who starred in X" patterns
                for pattern in keywords.get('actor_title_patterns', []):
                    import re
                    match = re.search(pattern, text_lower)
                    if match:
                        full_match = match.group(0)
                        # Use actor_title_phrases from config for prefix matching
                        actor_title_phrases = keywords.get('actor_title_phrases', [])
                        for prefix in actor_title_phrases:
                            if full_match.startswith(prefix):
                                title = full_match[len(prefix):].strip()
                                if title.endswith('?'):
                                    title = title[:-1].strip()
                                result['intents'].append('actor')
                                result['entities']['title'] = [title.title()]
                                result['confidence'] = 0.8
                                return result
                
                # If no specific pattern matched, extract title and return actor intent
                import re
                name_pattern = r'\b([A-Z][a-z]+(?:\s[A-Z][a-z]+){1,3})\b'
                matches = re.findall(name_pattern, question)
                if matches:
                    result['intents'].append('actor')
                    result['entities']['actor'] = [matches[-1].strip().title()]  # Get last name as actor
                    result['confidence'] = 0.7
                else:
                    result['intents'].append('actor')
                    result['confidence'] = 0.5
                return result
        
        # Check for rating questions
        rating_question_prefixes = keywords.get('rating_question_prefixes', [])
        if any(phrase in text_lower for phrase in rating_question_prefixes):
            # Extract title from rating questions like "What is the rating of X?"
            # Use patterns from language config
            import re
            rating_patterns = keywords.get('rating_question_patterns', [])
            for pattern in rating_patterns:
                match = re.search(pattern, text_lower, re.IGNORECASE)
                if match:
                    full_match = match.group(0)
                    rating_title_phrases = keywords.get('rating_title_phrases', [
                        'qual a avaliação de ', 'qual a nota de ',
                        'what is the rating of ', 'what is the score of '
                    ])
                    for prefix in rating_title_phrases:
                        if full_match.startswith(prefix):
                            title = full_match[len(prefix):].strip()
                            if title.endswith('?'):
                                title = title[:-1].strip()
                            result['intents'].append('rating')
                            result['entities']['title'] = [title.title()]
                            result['confidence'] = 0.8
                            return result
            
            # Fallback: extract title from the question
            import re
            name_pattern = r'\b([A-Z][a-z]+(?:\s[A-Z][a-z]+){1,3})\b'
            matches = re.findall(name_pattern, question)
            if matches:
                result['intents'].append('rating')
                result['entities']['title'] = [matches[-1].strip().title()]
                result['confidence'] = 0.7
            else:
                result['intents'].append('rating')
                result['confidence'] = 0.5
            return result
        
        # Check for director + actor combined queries FIRST (before any other pattern checks)
        combined_keywords = keywords.get('combined_query_keywords', {})
        director_keywords_combined = combined_keywords.get('director', [])
        actor_keywords_combined = combined_keywords.get('actor', [])
        
        has_director_combined = any(keyword in text_lower for keyword in director_keywords_combined)
        has_actor_combined = any(keyword in text_lower for keyword in actor_keywords_combined)
        
        if has_director_combined and has_actor_combined:
            import re
            name_pattern = r'\b([A-Z][a-z]+(?:\s[A-Z][a-z]+){1,3})\b'
            matches = re.findall(name_pattern, question)
            if len(matches) >= 2:
                result['intents'].append('title')
                result['entities']['director'] = [matches[0].strip()]
                result['entities']['actor'] = [matches[1].strip()]
                result['confidence'] = 0.9
                return result
            else:
                # If we don't get 2 matches, fall back to title intent with what we have
                if matches:
                    result['intents'].append('title')
                    result['entities']['title'] = [matches[-1].strip()]
                    result['confidence'] = 0.7
                    return result
        
        # Check for "directed by" patterns (looking for movies by director)
        directed_by_phrases = keywords.get('directed_by_phrases', [])
        if any(phrase in text_lower for phrase in directed_by_phrases):
            # Extract the director name - get the name AFTER "directed by"
            import re
            # Extract everything after "directed by" or similar phrases
            director_name = None
            for phrase in ['directed by ', 'dirigidos por ', 'dirigido por ']:
                if phrase in text_lower:
                    start_idx = text_lower.find(phrase) + len(phrase)
                    director_name = question[start_idx:].strip()
                    # Remove trailing question mark if present
                    if director_name.endswith('?'):
                        director_name = director_name[:-1].strip()
                    break
            
            if director_name:
                result['intents'].append('title')
                result['entities']['director'] = [director_name]
                result['confidence'] = 0.8
                return result
            
            # Fallback to regex if phrase matching fails
            name_pattern = r'\b([A-Z][a-z]+(?:\s[A-Z][a-z]+){1,3})\b'
            matches = re.findall(name_pattern, question)
            if matches:
                result['intents'].append('title')
                result['entities']['director'] = [matches[0].strip()]
                result['confidence'] = 0.8
                return result
        
        # Check if this contains actor/director keywords but isn't a "who" question
        # This handles cases like "Filmes protagonizados por X" which should return title intent
        # because we're looking for movies that have X as actor, not asking about X
        actor_keywords = keywords.get('actor', [])
        director_keywords = keywords.get('director', [])
        
        # Check for actor/director keywords in tokens OR in the full text (for multi-word phrases)
        has_actor_keyword = any(token.text.lower() in actor_keywords for token in doc) or \
                           any(keyword in text_lower for keyword in actor_keywords if len(keyword.split()) > 1)
        has_director_keyword = any(token.text.lower() in director_keywords for token in doc) or \
                              any(keyword in text_lower for keyword in director_keywords if len(keyword.split()) > 1)
        
        # Removed the logic that was intercepting actor/director queries
        # The specific logic for starring_phrases and directed_by_phrases will handle these cases
        
        # Check for "starring" patterns (looking for movies by actor)
        starring_phrases = keywords.get('starring_phrases', [])
        for phrase in starring_phrases:
            if phrase in text_lower:
                # Extract the actor name
                import re
                name_pattern = r'\b([A-Z][a-z]+(?:\s[A-Z][a-z]+){1,3})\b'
                matches = re.findall(name_pattern, question)
                if matches:
                    result['intents'].append('title')  # Intention is to find movies (title) with this actor
                    result['entities']['actor'] = [matches[0].strip()]  # But the entity is the actor
                    result['confidence'] = 0.8
                    return result
                    break
        
        title_in_title_phrases = keywords.get('title_in_title_phrases', [])
        for pattern in title_in_title_phrases:
            import re
            match = re.search(pattern, text_lower)
            if match:
                full_match = match.group(0)
                title_text = None
                
                # Handle Portuguese pattern: "com X no título"
                if full_match.startswith('com ') and (' no título' in full_match or ' no nome' in full_match):
                    start_idx = 4
                    end_phrase = ' no título' if ' no título' in full_match else ' no nome'
                    end_idx = full_match.find(end_phrase)
                    if end_idx > start_idx:
                        title_text = full_match[start_idx:end_idx].strip()
                
                # Handle English pattern: "with X in the title"
                elif 'with ' in full_match and ' in the title' in full_match:
                    start_idx = full_match.find('with ') + 5  # Skip "with "
                    end_idx = full_match.find(' in the title')
                    if end_idx > start_idx:
                        title_text = full_match[start_idx:end_idx].strip()
                
                if title_text:
                    title_capitalized = title_text.capitalize()
                    result['intents'].append('title')
                    result['entities']['title'] = [title_capitalized]
                    result['confidence'] = 0.8
                    return result
        
        # Default: most queries are looking for movie titles
        result['intents'].append('title')
        
        # Extract person names for title queries
        import re
        name_pattern = r'\b([A-Z][a-z]+(?:\s[A-Z][a-z]+){1,3})\b'
        matches = re.findall(name_pattern, question)
        person_names = [match.strip() for match in matches]
        
        # Check if this is an actor/director query that wasn't caught by earlier patterns
        has_actor_keyword = any(keyword in text_lower for keyword in actor_keywords)
        has_director_keyword = any(keyword in text_lower for keyword in director_keywords)
        
        # Check for genre keywords in the question
        genre_keywords = keywords.get('genre', [])
        has_genre_keyword = any(keyword in text_lower for keyword in genre_keywords)
        
        # Extract genre from question
        genres_found = []
        for genre in genre_keywords:
            if genre in text_lower:
                genres_found.append(genre)
        
        # Extract year from question - fixed to capture full 4-digit year
        year_pattern = r'\b(?:19|20)\d{2}\b'
        year_matches = re.findall(year_pattern, question)
        years_found = year_matches  # Now captures full 4-digit year
        
        # Handle complex queries with multiple entities (genre + actor + year)
        # Check for patterns like "movies with [actor]" or "films with [actor]"
        with_actor_patterns = ['with ', 'featuring ', 'starring ']
        has_with_actor = any(pattern in text_lower for pattern in with_actor_patterns)
        
        # Check for common movie query patterns that imply actor search
        movie_indicators = ['movie', 'movies', 'film', 'films']
        has_movie_indicator = any(indicator in text_lower for indicator in movie_indicators)
        
        # Special handling for "show me" pattern which often indicates a search query
        show_me_pattern = text_lower.startswith('show me ')
        
        if ((has_genre_keyword or genres_found) and person_names and years_found) or \
           (has_with_actor and person_names and (genres_found or years_found)) or \
           (show_me_pattern and has_movie_indicator and person_names and (genres_found or years_found)):
            # This is likely a query like "Show me action movies with Brad Pitt from 2010"
            # or "action movies with Brad Pitt from 2010"
            # or "Show me movies with Brad Pitt"
            result['entities']['actor'] = person_names
            if genres_found:
                result['entities']['genre'] = genres_found
            if years_found:
                result['entities']['year'] = years_found
            result['confidence'] = 0.9
            return result
        
        if has_actor_keyword and has_director_keyword and len(person_names) >= 2:
            # Both actor and director mentioned
            result['entities']['director'] = [person_names[0]]
            result['entities']['actor'] = [person_names[1]]
            result['confidence'] = 0.8
        elif has_director_keyword and person_names:
            # Only director mentioned
            result['entities']['director'] = person_names
            result['confidence'] = 0.8
        elif has_actor_keyword and person_names:
            # Only actor mentioned
            # Intention is to find movies (title) with this actor - intent already set above
            result['entities']['actor'] = person_names  # But the entity is the actor
            result['confidence'] = 0.8
        elif person_names:
            # Generic title search with person names
            result['entities']['title'] = person_names
            result['confidence'] = 0.7
        else:
            result['confidence'] = 0.5
        
        return result
        
    except Exception as e:
        logger.error(f"Error classifying intents: {e}")
        return {
            'intents': ['unknown'],
            'entities': {},
            'language': language,
            'confidence': 0.0
        }