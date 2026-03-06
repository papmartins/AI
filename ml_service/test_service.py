#!/usr/bin/env python3

import sys
import os
from venv import logger
import requests
import json
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from src.language_detector import language_detector

# Global models variable for testing
_test_models = None
_test_models_loaded = False

def test_question(question, language_hint=None, show_all=False):
    """Test a question using the microservice and return results"""
    # Detect language
    language = language_hint or language_detector.detect_language(question)
    
    try:
        # Use direct function call instead of HTTP request
        import sys
        sys.path.append('ml_service')
        
        # Load spaCy models if not already loaded
        global _test_models, _test_models_loaded
        if not _test_models_loaded:
            import spacy
            import logging
            
            # Suppress logging output for cleaner test results
            logging.getLogger('src.intent_classifier').setLevel(logging.WARNING)
            
            _test_models = {}
            _test_models['pt'] = spacy.load('pt_core_news_sm')
            _test_models['en'] = spacy.load('en_core_web_sm')
            _test_models['es'] = spacy.load('es_core_news_sm')
            _test_models_loaded = True
        
        from src.intent_classifier import classify_intents
        from src.language_detector import language_detector
        
        result = classify_intents(question, language, _test_models, language_detector.languages)
        return result, show_all
    except Exception as e:
        print(f"Classification error: {e}")
        return None, show_all

# Check if we should show all results or only wrong ones
import argparse
parser = argparse.ArgumentParser()
parser.add_argument('--all', action='store_true', help='Show all results with differences')
args = parser.parse_args()
show_all = args.all

# MOVIE TITLE TESTS - From Laravel questions.php
print("\n" + "="*70)
print("MOVIE TITLE TESTS - From Laravel questions.php")
print("="*70)


wrong_count = 0
total_count = 0

# Test function that compares with expected results
def test_and_compare(question, language_hint=None):
    global wrong_count, total_count
    result, show_all_flag = test_question(question, language_hint, show_all)
    
    if result:
        total_count += 1
        expected = EXPECTED_RESULTS.get(question, {})
        
        # Check if result matches expected (case-insensitive and order-insensitive comparison)
        matches = True
        
        # Get expected result from dictionary
        expected = EXPECTED_RESULTS.get(question, {})
        
        # If expected is empty but result has content, it's a mismatch
        if not expected and (result.get('intents') or result.get('entities')):
            matches = False
        elif expected:
            # Check intents (case-insensitive and order-insensitive)
            result_intents = [intent.lower() for intent in result.get('intents', [])]
            expected_intents = [intent.lower() for intent in expected.get('intents', [])]
            if set(result_intents) != set(expected_intents):
                matches = False
            # Check entities (case-insensitive for string values)
            result_entities = result.get('entities', {})
            expected_entities = expected.get('entities', {})
            if set(result_entities.keys()) != set(expected_entities.keys()):
                matches = False
            else:
                for key in result_entities:
                    result_values = result_entities[key]
                    expected_values = expected_entities[key]
                    # Convert to lowercase for string comparison
                    if isinstance(result_values, list) and isinstance(expected_values, list):
                        result_lower = [str(v).lower() for v in result_values]
                        expected_lower = [str(v).lower() for v in expected_values]
                        if set(result_lower) != set(expected_lower):
                            matches = False
                            break
                    elif str(result_values).lower() != str(expected_values).lower():
                        matches = False
                        break
        
        if show_all_flag:
            # Show all tests with indicators when --all is True
            print(f"\n{'='*70}")
            print(f"Question: {question}")
            print(f"{'='*70}")
            print(f"Detected language: {result.get('language', 'unknown')}")
            print(f"Intents: {result.get('intents', [])}")
            print(f"Entities:")
            for entity_type, values in result.get('entities', {}).items():
                print(f"  {entity_type}: {values}")
            print(f"Confidence: {result.get('confidence', 0.0):.2f}")
            
            if not matches:
                wrong_count += 1
                print(f"❌ WRONG: {question}")
            else:
                print(f"✅ CORRECT: {question}")
            print(f"  Expected: {expected}")
            print(f"  Got: {result}")
            print()
        elif not matches:
            # When --all is False, only show wrong tests (no indicators)
            wrong_count += 1
            print(f"\n{'='*70}")
            print(f"Question: {question}")
            print(f"{'='*70}")
            print(f"Detected language: {result.get('language', 'unknown')}")
            print(f"Intents: {result.get('intents', [])}")
            print(f"Entities:")
            for entity_type, values in result.get('entities', {}).items():
                print(f"  {entity_type}: {values}")
            print(f"Confidence: {result.get('confidence', 0.0):.2f}")
            print(f"Expected: {expected}")
            print(f"Got: {result}")
            print()
    return result

# Expected results for comparison
EXPECTED_RESULTS = {
    "Filmes com Brad Pitt": {"intents": ["title"], "entities": {"actor": ["Brad Pitt"]}},
    "Filmes protagonizados por Bruce Willis": {"intents": ["title"], "entities": {"actor": ["Bruce Willis"]}},
    "Filmes dirigidos por Christopher Nolan": {"intents": ["title"], "entities": {"director": ["Christopher Nolan"]}},
    "Filmes com a atriz Charlize Theron": {"intents": ["title"], "entities": {"actor": ["Charlize Theron"]}},
    "Filmes com Die Hard no título": {"intents": ["title"], "entities": {"title": ["Die Hard"]}},
    "Recomende alguns filmes populares": {"intents": ["recommendation"], "entities": {}},
    "Quem dirigiu Mad Max?": {"intents": ["director"], "entities": {"title": ["Mad Max"]}},
    "Filmes protagonizados por Will Ferrell": {"intents": ["title"], "entities": {"actor": ["Will Ferrell"]}},
    "Quais filmes protagonizados por Ryan Gosling?": {"intents": ["title"], "entities": {"actor": ["Ryan Gosling"]}},
    "Filmes com Love no título": {"intents": ["title"], "entities": {"title": ["Love"]}},
    "Recomende filmes para assistir": {"intents": ["recommendation"], "entities": {}},
    "Quem protagonizou Inception?": {"intents": ["actor"], "entities": {"title": ["Inception"]}},
    "Qual é o elenco de Die Hard?": {"intents": ["actor"], "entities": {"title": ["Die Hard"]}},
    "Que filmes existem com o realizador George Miller e o ator Charlize Theron?": {"intents": ["title"], "entities": {"director": ["George Miller"], "actor": ["Charlize Theron"]}},
    "Qual o elenco e quem dirigiu Mad Max?": {"intents": ["actor", "director"], "entities": {"title": ["Mad Max"]}},
    "Qual a avaliação do Mad Max?": {"intents": ["rating"], "entities": {"title": ["Mad Max"]}},
    "Mostra-me filmes de ação com Brad Pitt de 2010": {"intents": ["title"], "entities": {"actor": ["Brad Pitt"],"year": ["2010"], "genre": ["ação"]}},
    "Dá-me filmes de ação": {"intents": ["title"], "entities": {"genre": ["ação"]}},

    "Movies with Brad Pitt": {"intents": ["title"], "entities": {"actor": ["Brad Pitt"]}},
    "Movies starring Bruce Willis": {"intents": ["title"], "entities": {"actor": ["Bruce Willis"]}},
    "Movies directed by Christopher Nolan": {"intents": ["title"], "entities": {"director": ["Christopher Nolan"]}},
    "Movies starring Charlize Theron": {"intents": ["title"], "entities": {"actor": ["Charlize Theron"]}},
    "Movies with Die Hard in the title": {"intents": ["title"], "entities": {"title": ["Die Hard"]}},
    "Who directed Mad Max?": {"intents": ["director"], "entities": {"title": ["Mad Max"]}},
    "Movies starring Will Ferrell": {"intents": ["title"], "entities": {"actor": ["Will Ferrell"]}},
    "Movies starring Ryan Gosling": {"intents": ["title"], "entities": {"actor": ["Ryan Gosling"]}},
    "Movies with Love in the title": {"intents": ["title"], "entities": {"title": ["Love"]}},
    "Recommend movies to watch": {"intents": ["recommendation"], "entities": {}},
    "Who is the director of The Dark Knight?": {"intents": ["director"], "entities": {"title": ["The Dark Knight"]}},
    "Who starred in Inception?": {"intents": ["actor"], "entities": {"title": ["Inception"]}},
    "What is the cast in Die Hard?": {"intents": ["actor"], "entities": {"title": ["Die Hard"]}},
    "What movies are there with director George Miller and actress Charlize Theron?": {"intents": ["title"], "entities": {"director": ["George Miller"], "actor": ["Charlize Theron"]}},
    "What is the cast and who directed Mad Max?": {"intents": ["actor", "director"], "entities": {"title": ["Mad Max"]}},
    "What is the rating of Mad Max?": {"intents": ["rating"], "entities": {"title": ["Mad Max"]}},
    "Show me action movies with Brad Pitt from 2010": {"intents": ["title"], "entities": {"actor": ["Brad Pitt"],"year": ["2010"], "genre": ["action"]}},
    "Give me action movies": {"intents": ["title"], "entities": {"genre": ["action"]}},
    
    "Dá-me filmes de documentários": {"intents": ["title"], "entities": {"genre": ["documentários"]}},
    "Que generos de filmes existem?": {"intents": ["genre"], "entities": {}},
    "Witch movies genre exists?": {"intents": ["genre"], "entities": {}},
    "Qual o genero do filme Die Hard?": {"intents": ["genre"], "entities": {"title": ["Die Hard"]}},
}

print("\n--- Question about Movies Tests ---")
# Run tests
test_and_compare("Filmes com Brad Pitt", "pt")
test_and_compare("Mostra-me filmes de ação com Brad Pitt de 2010", "pt")
test_and_compare("Qual o elenco e quem dirigiu Mad Max?", "pt")
test_and_compare("Filmes protagonizados por Bruce Willis", "pt")
test_and_compare("Filmes dirigidos por Christopher Nolan", "pt")
test_and_compare("Filmes com a atriz Charlize Theron", "pt")
test_and_compare("Filmes com Die Hard no título", "pt")
test_and_compare("Recomende alguns filmes populares", "pt")
test_and_compare("Quem dirigiu Mad Max?", "pt")
test_and_compare("Filmes protagonizados por Will Ferrell", "pt")
test_and_compare("Quais filmes protagonizados por Ryan Gosling?", "pt")
test_and_compare("Filmes com Love no título", "pt")
test_and_compare("Recomende filmes para assistir", "pt")
test_and_compare("Quem protagonizou Inception?", "pt")
test_and_compare("Qual é o elenco de Die Hard?", "pt")
test_and_compare("Qual a avaliação do Mad Max?", "pt")
test_and_compare("Que filmes existem com o realizador George Miller e o ator Charlize Theron?", "pt")

test_and_compare("Movies with Brad Pitt", "en")
test_and_compare("Show me action movies with Brad Pitt from 2010", "en")
test_and_compare("What is the cast and who directed Mad Max?", "en")
test_and_compare("Movies starring Bruce Willis", "en")
test_and_compare("Movies directed by Christopher Nolan", "en")
test_and_compare("Movies starring Charlize Theron", "en")
test_and_compare("Movies with Die Hard in the title", "en")
test_and_compare("Who directed Mad Max?", "en")
test_and_compare("Movies starring Will Ferrell", "en")
test_and_compare("Movies starring Ryan Gosling", "en")
test_and_compare("Movies with Love in the title", "en")
test_and_compare("Recommend movies to watch", "en")
test_and_compare("Who is the director of The Dark Knight?", "en")
test_and_compare("Who starred in Inception?", "en")
test_and_compare("What is the cast in Die Hard?", "en")
test_and_compare("What is the rating of Mad Max?", "en")
test_and_compare("What movies are there with director George Miller and actress Charlize Theron?", "en")

test_and_compare("Dá-me filmes de documentários", "pt")
test_and_compare("Que generos de filmes existem?", "pt")
test_and_compare("Qual o genero do filme Die Hard?", "pt")
test_and_compare("Witch movies genre exists?", "en")

print(f"\n{'='*70}")
print(f"SUMMARY: {wrong_count} wrong out of {total_count} tests")
if wrong_count == 0:
    print("🎉 All tests passed!")
else:
    print(f"⚠️ {wrong_count} tests failed")
print(f"{'='*70}")

exit(0)


print("\n--- Spanish Movie Title Tests ---")
test_question("Películas protagonizadas por Bruce Willis", "es")
test_question("Películas dirigidas por Christopher Nolan", "es")
test_question("Películas con la actriz Charlize Theron", "es")
test_question("Películas con Die Hard en el título", "es")
test_question("¿Quién dirigió Mad Max?", "es")
test_question("Películas protagonizadas por Will Ferrell", "es")
test_question("Películas protagonizadas por Ryan Gosling", "es")
test_question("Películas con Love en el título", "es")


# Test cases organized by language and complexity
print("="*70)
print("COMPREHENSIVE ML MICROSERVICE TEST SUITE")
print("="*70)

# PORTUGUESE TESTS
print("\n" + "="*70)
print("PORTUGUESE TESTS")
print("="*70)

# Frontend suggestions - Portuguese
print("\n--- Frontend Suggestions (PT) ---")
test_question("Quais filmes existem com o realizador George Miller e o ator Charlize Theron?", "pt")
test_question("Quais filmes têm o ator Tom Hanks?", "pt")
test_question("Quais são os filmes de ação com o diretor Michael Bay e o ator Mark Wahlberg?", "pt")
test_question("Quais filmes de comédia você recomenda?", "pt")
test_question("Quais são os melhores filmes de 2020?", "pt")
test_question("Quais filmes foram dirigidos por Christopher Nolan?", "pt")

# Additional Portuguese tests
print("\n--- Additional Portuguese Tests ---")
test_question("Quais filmes de terror têm boa avaliação?", "pt")
test_question("Quais são os filmes protagonizados pela atriz Meryl Streep?", "pt")
test_question("Quais filmes de ficção científica foram lançados em 2010?", "pt")
test_question("Quais filmes de romance você recomenda para assistir no fim de semana?", "pt")

# ENGLISH TESTS
print("\n" + "="*70)
print("ENGLISH TESTS")
print("="*70)

# Frontend suggestions - English
print("\n--- Frontend Suggestions (EN) ---")
test_question("What movies are there with director George Miller and actress Charlize Theron?", "en")
test_question("What movies star Tom Hanks?", "en")
test_question("What are the action movies with director Michael Bay and actor Mark Wahlberg?", "en")
test_question("What comedy movies do you recommend?", "en")
test_question("What are the best movies of 2020?", "en")
test_question("What movies were directed by Christopher Nolan?", "en")

# Additional English tests
print("\n--- Additional English Tests ---")
test_question("What horror movies have good ratings?", "en")
test_question("What movies star actress Meryl Streep?", "en")
test_question("What sci-fi movies were released in 2010?", "en")
test_question("What romance movies do you recommend for the weekend?", "en")

# SPANISH TESTS
print("\n" + "="*70)
print("SPANISH TESTS")
print("="*70)

# Frontend suggestions - Spanish
print("\n--- Frontend Suggestions (ES) ---")
test_question("¿Qué películas hay con el director George Miller y la actriz Charlize Theron?", "es")
test_question("¿Qué películas protagoniza Tom Hanks?", "es")
test_question("¿Cuáles son las películas de acción con el director Michael Bay y el actor Mark Wahlberg?", "es")
test_question("¿Qué películas de comedia me recomiendas?", "es")
test_question("¿Cuáles son las mejores películas de 2020?", "es")
test_question("¿Qué películas fueron dirigidas por Christopher Nolan?", "es")

# Additional Spanish tests
print("\n--- Additional Spanish Tests ---")
test_question("¿Qué películas de terror tienen buena calificación?", "es")
test_question("¿Qué películas protagoniza la actriz Meryl Streep?", "es")
test_question("¿Qué películas de ciencia ficción se estrenaron en 2010?", "es")
test_question("¿Qué películas de romance me recomiendas para el fin de semana?", "es")

# COMPLEX TESTS - Multiple intents and entities
print("\n" + "="*70)
print("COMPLEX TESTS - Multiple Intents and Entities")
print("="*70)

print("\n--- Portuguese Complex Tests ---")
test_question("Quais filmes de ação com boa avaliação têm o ator Keanu Reeves e foram dirigidos por Chad Stahelski?", "pt")
test_question("Quais filmes de comédia romântica lançados em 2022 você recomenda com a atriz Jennifer Lawrence?", "pt")

print("\n--- English Complex Tests ---")
test_question("What action movies with good ratings star actor Keanu Reeves and were directed by Chad Stahelski?", "en")
test_question("What romantic comedy movies released in 2022 do you recommend starring actress Jennifer Lawrence?", "en")

print("\n--- Spanish Complex Tests ---")
test_question("¿Qué películas de acción con buena calificación tienen al actor Keanu Reeves y fueron dirigidas por Chad Stahelski?", "es")
test_question("¿Qué películas de comedia romántica estrenadas en 2022 me recomiendas con la actriz Jennifer Lawrence?", "es")

# EDGE CASES
print("\n" + "="*70)
print("EDGE CASES")
print("="*70)

print("\n--- Short Questions ---")
test_question("Filmes com Tom Cruise?", "pt")
test_question("Movies with Tom Cruise?", "en")
test_question("Películas con Tom Cruise?", "es")

print("\n--- Questions without clear intent ---")
test_question("O que você pode me dizer sobre filmes?", "pt")
test_question("What can you tell me about movies?", "en")
test_question("¿Qué puedes decirme sobre películas?", "es")

print("\n--- Questions with typos ---")
test_question("Quais filmess têm o atoor Tom Hanks?", "pt")
test_question("What moviess star the acttor Tom Hanks?", "en")

print("\n" + "="*70)
print("TEST SUITE COMPLETED!")
print("="*70)
