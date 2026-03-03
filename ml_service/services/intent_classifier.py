"""Intent Classification Service with graceful fallback when heavy ML libs are unavailable.

This module attempts to import sentence-transformers and transformers at runtime. If
those imports fail (version incompatibility or missing packages in the container), the
class will still initialize and provide a rule-based fallback classifier so the
microservice stays operational.
"""

import logging
from typing import Optional, Dict, List

logger = logging.getLogger(__name__)

# Import language service
from .language_service import language_service

# Try to import heavy ML libs. If they fail, we will run in degraded mode.
HAS_ML = True
try:
    from sentence_transformers import SentenceTransformer
    from transformers import pipeline
    import numpy as np
except Exception as e:
    logger.warning(f"ML libraries not available or failed to import: {e}. Running fallback mode.")
    HAS_ML = False


class IntentClassifier:
    """Classifier that uses ML when available, otherwise falls back to templates."""

    def __init__(self):
        global HAS_ML
        # Load intent data from language service
        self.intent_data = {}
        available_langs = language_service.get_available_languages()
        logger.info(f"Available languages from language service: {available_langs}")
        
        for lang in available_langs:
            lang_data = language_service.get_language_data(lang)
            self.intent_data[lang] = lang_data
            logger.info(f"Loaded data for {lang}: {list(lang_data.keys())}")
        
        # Set default intent labels and templates
        self.intent_labels = self.intent_data.get('en', {}).get('intent_labels', [
            "actor", "director", "genre", "year", "rating", "title", "recommendation"
        ])
        logger.info(f"Intent labels: {self.intent_labels}")
        
        # Simple templates for fallback - use English as default
        self.intent_templates = self.intent_data.get('en', {}).get('intent_templates', {
            "actor": ["movies with", "films starring", "protagonist", "actor", "star", "cast"],
            "director": ["directed by", "director", "filmmaker"],
            "genre": ["action", "comedy", "horror", "genre", "drama", "adventure"],
            "year": ["released in", "from", "year"],
            "rating": ["highly rated", "best", "rated", "score"],
            "title": ["title", "called", "named"],
            "recommendation": ["recommend", "suggest", "popular", "recommends", "suggests"]
        })
        logger.info(f"Intent templates: {self.intent_templates}")
        
        # Load context patterns for better intent detection
        self.context_patterns = self.intent_data.get('en', {}).get('intent_context_patterns', [])
        logger.info(f"Context patterns: {self.context_patterns}")

        if HAS_ML:
            try:
                # Load models lazily; wrap in try/catch to avoid crashing on init
                self.sentence_model = SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')
                self.zero_shot_classifier = pipeline("zero-shot-classification", model="facebook/bart-large-mnli")
                self.lang_detect = pipeline("text-classification", model="papluca/xlm-roberta-base-language-detection")
            except Exception as e:
                logger.warning(f"Failed to initialize ML models: {e}. Falling back to rules.")
                HAS_ML = False



    def detect_language(self, text: str) -> str:
        """Use centralized language detection service"""
        return language_service.detect_language(text)

    def classify(self, question: str, language: Optional[str] = None) -> Dict:
        if not language:
            language = self.detect_language(question)
        
        # Update templates and context patterns based on detected language
        self.intent_templates = self.intent_data.get(language, {}).get('intent_templates', self.intent_templates)
        self.context_patterns = self.intent_data.get(language, {}).get('intent_context_patterns', [])
        
        logger.info(f"Language: {language}")
        logger.info(f"Context patterns loaded: {len(self.context_patterns)} patterns")
        logger.info(f"Context patterns: {self.context_patterns}")

        if HAS_ML:
            try:
                result = self.zero_shot_classifier(question, self.intent_labels, multi_class=False)
                intent = result['labels'][0]
                confidence = float(result['scores'][0])
                if confidence < 0.25:
                    intent, confidence = self._template_match(question)
                return {"intent": intent, "confidence": confidence, "language": language}
            except Exception as e:
                logger.warning(f"Zero-shot failed at runtime: {e}. Using template fallback.")

        intent, confidence = self._template_match(question)
        return {"intent": intent, "confidence": confidence, "language": language}

    def classify_multiple(self, question: str, language: Optional[str] = None) -> Dict:
        logger.info(f"=== Classifying multiple intents ===")
        logger.info(f"Input question: {question}")
        logger.info(f"Input language: {language}")
        
        # Log specific patterns for debugging
        if "action" in question.lower():
            logger.info("DEBUG: Found 'action' in question - should match genre")
        if "with" in question.lower():
            logger.info("DEBUG: Found 'with' in question - should match actor")
        
        if not language:
            detected_lang = self.detect_language(question)
            logger.info(f"Auto-detected language: {detected_lang}")
            language = detected_lang
        else:
            logger.info(f"Using provided language: {language}")
        
        # Update templates and context patterns based on detected language
        self.intent_templates = self.intent_data.get(language, {}).get('intent_templates', self.intent_templates)
        self.context_patterns = self.intent_data.get(language, {}).get('intent_context_patterns', [])
        logger.info(f"Loaded templates for {language}: {self.intent_templates}")
        logger.info(f"Loaded context patterns for {language}: {self.context_patterns}")
        
        # Determine conjunction used in the question
        conjunction_used = None
        lower_question = question.lower()
        for conj in [" and ", " or ", " & "]:
            if conj in lower_question:
                conjunction_used = conj.strip()
                break

        if HAS_ML:
            try:
                result = self.zero_shot_classifier(question, self.intent_labels, multi_class=True)
                labels = result.get('labels', [])
                scores = result.get('scores', [])
                filtered = [(l, s) for l, s in zip(labels, scores) if s > 0.15]
                if not filtered and labels:
                    filtered = [(labels[0], scores[0])]
                intents = [i for i, _ in filtered[:2]]
                confidences = [float(c) for _, c in filtered[:2]]
                condition = 'OR' if any(x in question.lower() for x in [' ou ', ' or ']) else 'AND'
                return {"intents": intents, "confidences": confidences, "condition": condition, "language": language}
            except Exception as e:
                logger.warning(f"Multiple intent ML failed: {e}")

        # Fallback: rule-based detection with context awareness
        detected = []
        confidences = []
        lower = question.lower()
        
        # Use language-specific context patterns
        context_matched = False
        # if hasattr(self, 'context_patterns') and self.context_patterns:
        #     logger.info(f"Checking context patterns for question: {question}")
            
        #     # Find all pattern matches with their positions to preserve order
        #     pattern_matches = []
        #     for pattern_data in self.context_patterns:
        #         patterns = pattern_data['pattern']
        #         intent = pattern_data['intent']
        #         confidence = pattern_data['confidence']
                
        #         logger.info(f"Checking pattern {patterns} for intent {intent}")
                
        #         # Check if any pattern in the list is found in the question
        #         pattern_matched = False
        #         match_position = -1
        #         if isinstance(patterns, list):
        #             # For list patterns, check if ANY pattern matches
        #             for p in patterns:
        #                 if p.lower() in lower:
        #                     pattern_matched = True
        #                     match_position = lower.find(p.lower())
        #                     logger.info(f"Matched sub-pattern '{p}' at position {match_position} in '{question}'")
        #                     break
        #         else:
        #             # For single string patterns
        #             if patterns.lower() in lower:
        #                 pattern_matched = True
        #                 match_position = lower.find(patterns.lower())
        #                 logger.info(f"Matched pattern '{patterns}' at position {match_position} in '{question}'")
                
        #         if pattern_matched and intent not in [m[0] for m in pattern_matches]:
        #             pattern_matches.append((intent, confidence, match_position))
            
        #     # Sort matches by their position in the question to preserve order
        #     pattern_matches.sort(key=lambda x: x[2])
            
        #     # Add sorted matches to detected intents
        #     for intent, confidence, _ in pattern_matches:
        #         detected.append(intent)
        #         confidences.append(confidence)
        #         context_matched = True
        #         logger.info(f"Matched intent {intent} with confidence {confidence} (sorted by position)")
        
        if not context_matched:
            logger.info("No context patterns matched, using fallback logic")
            # Enhanced fallback logic
            for intent in self.intent_templates.keys():
                tokens = self.intent_templates[intent]
                for token in tokens:
                    # More flexible matching - check if token is in the question
                    if token.lower() in lower and token.strip() not in '0123456789':
                        if intent not in detected:  # Avoid duplicates
                            detected.append(intent)
                            confidences.append(0.5)
                            logger.info(f"Fallback matched intent {intent} with token '{token}'")
                            break  # Move to next intent after first match
        
        if not detected:
            detected = ['unknown']
            confidences = [0.0]
        condition = 'OR' if any(x in lower for x in [' ou ', ' or ']) else 'AND'
        return {"intents": detected[:2], "confidences": confidences[:2], "condition": condition, "language": language, "conjunction": conjunction_used}

    def _template_match(self, question: str) -> tuple:
        lower = question.lower()
        best = ('unknown', 0.0)
        for intent, templates in self.intent_templates.items():
            score = 0
            for t in templates:
                if t.isdigit():
                    continue
                if t in lower:
                    score += 1
            if score > best[1]:
                best = (intent, float(score) / max(len(templates), 1))
        return best

