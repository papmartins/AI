"""Entity Extraction Service with graceful fallback when spaCy unavailable."""

import logging
from typing import List, Dict, Optional
import re
import os
import json

logger = logging.getLogger(__name__)

# Guarded import of spaCy to avoid crash if not installed
HAS_SPACY = True
try:
    import spacy
except Exception as e:
    logger.warning(f"spaCy not available: {e}. Using regex-only entity extraction.")
    HAS_SPACY = False

# Import language service
from .language_service import language_service


class EntityExtractor:
    """Extract entities using NER (spaCy) when available, otherwise regex-only fallback."""
    
    def __init__(self):
        self.has_spacy = HAS_SPACY
        # Initialize language-specific patterns
        self.movie_prefixes = []
        self.person_prefixes = []
        self.trailing_phrases = []
        self.conjunctions = []
        self.role_indicators = []
        self.language_specific_phrases = []
        # Year pattern
        self.year_pattern = r'\b(19|20)\d{2}\b'
        # Load default patterns (will be overridden when detect_language is called)
        self.load_entity_extraction_patterns("en")
        
        # Load multilingual spaCy models if available, else use blank
        if self.has_spacy:
            try:
                self.nlp_pt = spacy.load("pt_core_news_sm")
            except Exception as e:
                logger.warning(f"pt_core_news_sm not found: {e}, using blank model")
                self.nlp_pt = spacy.blank("pt")
            
            try:
                self.nlp_en = spacy.load("en_core_web_sm")
            except Exception as e:
                logger.warning(f"en_core_web_sm not found: {e}, using blank model")
                self.nlp_en = spacy.blank("en")
            
            try:
                self.nlp_es = spacy.load("es_core_news_sm")
            except Exception as e:
                logger.warning(f"es_core_news_sm not found: {e}, using blank model")
                self.nlp_es = spacy.blank("es")
        else:
            self.nlp_pt = None
            self.nlp_en = None
            self.nlp_es = None
        
        # Movie title patterns
        self.movie_title_pattern = r'"([^"]+)"|\'([^\']+)\'|\b(?:The\s+|A\s+)?[A-Z][a-zA-Z\s:&0-9]+(?=\s+\(|\s+\d{4}|$)'
    
    def load_entity_extraction_patterns(self, language: str):
        """Load language-specific patterns for entity extraction"""
        try:
            lang_file = f"lang/{language}.json"
            with open(lang_file, 'r', encoding='utf-8') as f:
                lang_data = json.load(f)
                patterns = lang_data.get('entity_extraction_patterns', {})
                self.movie_prefixes = patterns.get('movie_prefixes', [])
                self.person_prefixes = patterns.get('person_prefixes', [])
                self.trailing_phrases = patterns.get('trailing_phrases', [])
                
                # Load entity split expressions
                split_data = lang_data.get('entity_split_expressions', {})
                self.conjunctions = split_data.get('conjunctions', [])
                self.role_indicators = split_data.get('role_indicators', [])
                self.language_specific_phrases = split_data.get('language_specific', [])
        except (FileNotFoundError, json.JSONDecodeError):
            # Fallback to English patterns if language file not found
            self.movie_prefixes = [
                "movies with ", "films about ", "shows featuring ", "series containing "
            ]
            self.person_prefixes = [
                "movies starring ", "movies directed by ", "films by "
            ]
            self.trailing_phrases = [" in the title", " featuring "]
            self.conjunctions = [" and ", " or ", " & "]
            self.role_indicators = ["director ", "actor ", "starring ", "with director ", "with actor "]
            self.language_specific_phrases = [" with ", " featuring ", " starring "]
    
    def _split_complex_query(self, text: str, language: str) -> Dict:
        """Split complex queries into simpler parts using language-specific expressions"""
        parts = [text]
        conjunction_used = None
        logger.info(f"Splitting query: {text}")
        logger.info(f"Conjunctions: {self.conjunctions}")
        logger.info(f"Role indicators: {self.role_indicators}")
        
        # First, check if this looks like a complex query with multiple roles
        lower_text = text.lower()
        
        # Check for Portuguese patterns
        if language == 'pt':
            if ('realizador' in lower_text or 'diretor' in lower_text or 'ator' in lower_text) and ' e ' in lower_text:
                # Split Portuguese query like "filmes com o realizador X e o ator Y"
                parts = []
                
                # Extract director
                if 'realizador' in lower_text or 'diretor' in lower_text:
                    director_part = text.split('realizador')[1] if 'realizador' in lower_text else text.split('diretor')[1]
                    director_name = director_part.split(' e ')[0].strip()
                    if director_name:
                        parts.append(f"realizador {director_name}")
                
                # Extract actor
                if 'ator' in lower_text:
                    actor_part = text.split('ator')[1]
                    actor_name = actor_part.strip()
                    if actor_name:
                        parts.append(f"ator {actor_name}")
                
                logger.info(f"Split Portuguese query into: {parts}")
                return parts if parts else [text]
        
        # Check for English patterns
        elif language == 'en':
            # Handle simple patterns that don't need splitting
            # These will be processed by the entity extraction logic below
            pass  # No splitting needed for simple queries
        
        # Split by conjunctions for simpler cases
        for conj in self.conjunctions:
            if conj in lower_text:
                parts = []
                for part in text.split(conj):
                    stripped_part = part.strip()
                    if stripped_part:
                        parts.append(stripped_part)
                conjunction_used = conj.strip()
                logger.info(f"Split by conjunction '{conj}': {parts}")
                return {"parts": parts, "conjunction": conjunction_used}
        
        # Split by role indicators for simpler cases
        for indicator in self.role_indicators:
            if indicator.lower() in lower_text:
                # Check if this is a simple pattern that shouldn't be split
                # Simple patterns are those without conjunctions
                has_conjunction = any(conj.lower() in lower_text for conj in self.conjunctions)
                
                if not has_conjunction:
                    # Don't split simple patterns, let the entity extraction handle them
                    logger.info(f"Not splitting simple pattern: {text}")
                    return [text]
                
                # For complex patterns with conjunctions, split them
                parts = []
                remaining = text
                while indicator.lower() in remaining.lower():
                    before, after = remaining.split(indicator.lower(), 1)
                    if before.strip():
                        parts.append(before.strip())
                    # Extract the part after the role indicator
                    person_part = after.split(' e ' if language == 'pt' else ' and ' if language == 'en' else ' y ')[0]
                    person_part = person_part.split(' ou ' if language == 'pt' else ' or ' if language == 'en' else ' o ')[0]
                    # Remove trailing punctuation and conjunctions
                    person_part = person_part.rstrip('?,!')
                    clean_part = indicator.strip() + " " + person_part.strip()
                    parts.append(clean_part)
                    remaining = after[len(person_part):].strip()
                if remaining:
                    parts.append(remaining)
                logger.info(f"Split by role indicator '{indicator}': {parts}")
                return parts
        
        # Handle complex English queries like "What movies are there with director X and actress Y?"
        if language == 'en' and ' with ' in lower_text and ' and ' in lower_text:
            parts = []
            # Extract the part after "with"
            with_part = text.split(' with ')[1] if ' with ' in text else text
            # Split by "and"
            and_parts = with_part.split(' and ')
            for and_part in and_parts:
                # Check for role indicators
                for indicator in self.role_indicators:
                    if indicator.lower() in and_part.lower():
                        person_name = and_part[len(indicator):].strip().rstrip('?,!')
                        if person_name:
                            parts.append(f"{indicator.strip()} {person_name}")
                        break
            logger.info(f"Split complex English query into: {parts}")
            return parts if parts else [text]
        
        logger.info(f"No splitting applied, returning original: {parts}")
        return parts
    
    def detect_language(self, text: str) -> str:
        """Use centralized language detection service"""
        return language_service.detect_language(text)
    
    def extract(self, text: str, language: Optional[str] = None) -> List[Dict]:
        """
        Extract entities from text.
        Uses spaCy NER when available, otherwise falls back to regex patterns.
        Returns: [{"text": "...", "label": "PERSON|GPE|MOVIE|DATE|etc", "start": 0, "end": 5}, ...]
        """
        if not language:
            language = self.detect_language(text)
        
        # Load language-specific patterns
        self.load_entity_extraction_patterns(language)
        logger.info(f"Using language: {language}")
        logger.info(f"Loaded patterns - movie_prefixes: {self.movie_prefixes}")
        logger.info(f"Loaded patterns - person_prefixes: {self.person_prefixes}")
        logger.info(f"Loaded patterns - conjunctions: {self.conjunctions}")
        logger.info(f"Loaded patterns - role_indicators: {self.role_indicators}")
        
        entities = []
        # spaCy NER (if available)
        if self.has_spacy:
            try:
                nlp = self.nlp_pt if language == 'pt' else (
                    self.nlp_es if language == 'es' else self.nlp_en
                )
                doc = nlp(text)
                for ent in doc.ents:
                    entities.append({
                        "text": ent.text,
                        "label": ent.label_,
                        "start": ent.start_char,
                        "end": ent.end_char
                    })
            except Exception as e:
                logger.warning(f"spaCy NER failed: {e}")
        
        # Extract years
        for match in re.finditer(self.year_pattern, text):
            year = match.group(0)
            entities.append({
                "text": year,
                "label": "DATE",
                "start": match.start(),
                "end": match.end()
            })
        
        # Split complex queries into parts before processing
        split_result = self._split_complex_query(text, language)
        if isinstance(split_result, dict):
            query_parts = split_result["parts"]
            conjunction_used = split_result.get("conjunction")
        else:
            query_parts = split_result
            conjunction_used = None
        logger.info(f"Split query '{text}' into parts: {query_parts}")
        if conjunction_used:
            logger.info(f"Conjunction used: {conjunction_used}")
        
        # Process each part separately
        for part in query_parts:
            # First check for person patterns before applying regex
            lower_part = part.lower()
            
            # Check for person patterns (actor, director, etc.)
            person_extracted = False
            for prefix in self.person_prefixes:
                if lower_part.startswith(prefix.lower()):
                    person_name = part[len(prefix):].strip()
                    # Clean up
                    for phrase in self.trailing_phrases:
                        person_name = re.sub(re.escape(phrase) + '$', '', person_name, flags=re.IGNORECASE)
                    person_name = person_name.rstrip('.,!?')
                    
                    if person_name:
                        entities.append({
                            "text": person_name,
                            "label": "PERSON",
                            "start": part.find(person_name),
                            "end": part.find(person_name) + len(person_name)
                        })
                        person_extracted = True
                        logger.info(f"Extracted person: {person_name} from '{part}'")
                        break
            
            # Also check for person patterns in the middle of the part
            # e.g., "with director George Miller" should extract "George Miller"
            if not person_extracted:
                for prefix in self.person_prefixes:
                    if f" {prefix.lower()}" in f" {lower_part}":
                        # Find the prefix in the part
                        prefix_pos = lower_part.find(f" {prefix.lower()}")
                        if prefix_pos >= 0:
                            # Extract the name after the prefix
                            name_start = prefix_pos + len(prefix) + 1  # +1 for the space
                            person_name = part[name_start:].strip()
                            # Clean up
                            for phrase in self.trailing_phrases:
                                person_name = re.sub(re.escape(phrase) + '$', '', person_name, flags=re.IGNORECASE)
                            person_name = person_name.rstrip('.,!?')
                            
                            if person_name:
                                entities.append({
                                    "text": person_name,
                                    "label": "PERSON",
                                    "start": part.find(person_name),
                                    "end": part.find(person_name) + len(person_name)
                                })
                                person_extracted = True
                                logger.info(f"Extracted person from middle: {person_name} from '{part}'")
                                break
            
            if person_extracted:
                continue
            
            # Check for movie patterns
            movie_extracted = False
            for prefix in self.movie_prefixes:
                if lower_part.startswith(prefix.lower()):
                    movie_title = part[len(prefix):].strip()
                    # Clean up trailing phrases
                    for phrase in self.trailing_phrases:
                        movie_title = re.sub(re.escape(phrase) + '$', '', movie_title, flags=re.IGNORECASE)
                    movie_title = movie_title.rstrip('.,!?')
                    
                    if movie_title:
                        entities.append({
                            "text": movie_title,
                            "label": "MOVIE",
                            "start": part.find(movie_title),
                            "end": part.find(movie_title) + len(movie_title)
                        })
                        movie_extracted = True
                        logger.info(f"Extracted movie: {movie_title} from '{part}'")
                        break
            
            if movie_extracted:
                continue
            
            # Apply regex patterns for quoted titles, etc.
            for match in re.finditer(self.movie_title_pattern, part):
                title = match.group(1) or match.group(2) or match.group(0)
                if len(title.strip()) > 2:
                    lower_title = title.lower()
                    
                    # Patterns for extracting movie titles
                    if any(lower_title.startswith(prefix.lower()) for prefix in self.movie_prefixes):
                        # Extract movie title from these patterns
                        actual_title = title
                        for prefix in self.movie_prefixes:
                            if lower_title.startswith(prefix.lower()):
                                actual_title = title[len(prefix):].strip()
                                break
                        
                        # Clean up trailing phrases
                        for phrase in self.trailing_phrases:
                            actual_title = re.sub(re.escape(phrase) + '$', '', actual_title, flags=re.IGNORECASE)
                        actual_title = actual_title.rstrip('.,!?')
                        
                        if actual_title:
                            entities.append({
                                "text": actual_title,
                                "label": "MOVIE",
                                "start": match.start(),
                                "end": match.end()
                            })
                    
                    # Patterns for extracting person names (directors, actors)
                    elif any(lower_title.startswith(prefix.lower()) for prefix in self.person_prefixes):
                        person_name = title
                        for prefix in self.person_prefixes:
                            if lower_title.startswith(prefix.lower()):
                                person_name = title[len(prefix):].strip()
                                break
                        
                        # Clean up
                        for phrase in self.trailing_phrases:
                            person_name = re.sub(re.escape(phrase) + '$', '', person_name, flags=re.IGNORECASE)
                        person_name = person_name.rstrip('.,!?')
                        
                        if person_name:
                            entities.append({
                                "text": person_name,
                                "label": "PERSON",
                                "start": match.start() + len(prefix),
                                "end": match.end()
                            })
                    else:
                        # Normal movie title extraction
                        entities.append({
                            "text": title.strip(),
                            "label": "MOVIE",
                            "start": match.start(),
                            "end": match.end()
                        })
        
        # Remove duplicates
        seen = set()
        unique_entities = []
        for ent in entities:
            key = (ent["text"].lower(), ent["label"])
            if key not in seen:
                unique_entities.append(ent)
                seen.add(key)
        
        return unique_entities, conjunction_used

    
    # def extract_person_name(self, text: str, language: Optional[str] = None) -> Optional[str]:
    #     """Extract first person name from text"""
    #     entities = self.extract(text, language)
    #     for ent in entities:
    #         if ent["label"] == "PERSON":
    #             return ent["text"]
    #     return None
    
    # def extract_title_keywords(self, text: str, language: Optional[str] = None) -> List[str]:
    #     """Extract movie title keywords"""
    #     entities = self.extract(text, language)
    #     titles = [ent["text"] for ent in entities if ent["label"] == "MOVIE"]
    #     return titles
    
    # def extract_year(self, text: str) -> Optional[int]:
    #     """Extract first year mentioned"""
    #     for match in re.finditer(self.year_pattern, text):
    #         return int(match.group(0))
    #     return None
