#!/usr/bin/env python3
"""
Quick validation script for NLP Microservice
Tests all endpoints without needing Docker
"""

import sys
import json
from services.intent_classifier import IntentClassifier
from services.entity_extractor import EntityExtractor
from services.semantic_search import SemanticSearch


def test_intent_classification():
    print("\n" + "="*60)
    print("Testing Intent Classification")
    print("="*60)
    
    classifier = IntentClassifier()
    
    test_cases = [
        ("What movies has Tom Cruise starred in?", "actor"),
        ("Show me horror movies from 2020", "genre"),
        ("Who directed Die Hard?", "director"),
        ("Filmes de 2015", "year"),  # Portuguese
        ("Películas con Brad Pitt", "actor"),  # Spanish
        ("Best rated movies", "rating"),
    ]
    
    for question, expected_intent in test_cases:
        result = classifier.classify(question)
        status = "✓" if result["intent"] == expected_intent else "✗"
        print(f"{status} '{question}' → {result['intent']} (confidence: {result['confidence']:.2f})")


def test_multiple_intents():
    print("\n" + "="*60)
    print("Testing Multiple Intent Classification")
    print("="*60)
    
    classifier = IntentClassifier()
    test_cases = [
    "Movies starring Bruce Willis",
    "Filmes protagonizados por Bruce Willis",
    "Movies directed by Christopher Nolan",
    "Filmes realizados por Christopher Nolan",
    "Movies starring Charlize Theron or directed by Tarantino",
    "Filmes protagonizados por Charlize Theron ou realizados por Tarantino",
    "Movies with Die Hard in the title",
    "Filmes com Duro de Matar no título",
    "Recommend some popular movies",
    "Recomenda alguns filmes populares",
    "Who directed Mad Max?",
    "Quem realizou Mad Max?",
    "Movies starring Will Ferrell",
    "Filmes protagonizados por Will Ferrell",
    "Movies starring Ryan Gosling",
    "Filmes protagonizados por Ryan Gosling",
    "Movies with Love in the title",
    "Filmes com Amor no título",
    "Recommend movies to watch",
    "Recomenda filmes para ver",
    "Show me action movies with Tom Cruise from 2020",
    "Mostra-me filmes de ação com Tom Cruise de 2020",
    "Comedy films directed by Tarantino",
    "Filmes de comédia realizados por Tarantino",
    "Best rated horror movies from 2015",
    "Melhores filmes de terror de 2015",
]

    for question in test_cases:
        result = classifier.classify_multiple(question)
        print(f"\n'{question}'")
        print(f"  Intents: {result['intents']}")
        print(f"  Confidences: {result['confidences']}")
        print(f"  conjunction: {result['conjunction']}")


def test_entity_extraction():
    print("\n" + "="*60)
    print("Testing Entity Extraction")
    print("="*60)
    
    extractor = EntityExtractor()
    test_cases = [
    "Tom Cruise starred in Mission: Impossible (1996)",
    "Tom Cruise protagonizou Missão Impossível (1996)",
    "Christopher Nolan directed Inception in 2010",
    "Christopher Nolan realizou A Origem em 2010",
    "Action movies with Tom Hanks",
    "Filmes de ação com Tom Hanks",
    "Movies starring Bruce Willis",
    "Filmes protagonizados por Bruce Willis",
    "Movies directed by Christopher Nolan",
    "Filmes realizados por Christopher Nolan",
    "Movies starring Charlize Theron or directed by Tarantino",
    "Filmes protagonizados por Charlize Theron ou realizados por Tarantino",
    "Movies with Die Hard in the title",
    "Filmes com Duro de Matar no título",
    "Recommend some popular movies",
    "Recomenda alguns filmes populares",
    "Who directed Mad Max?",
    "Quem realizou Mad Max?",
    "Movies starring Will Ferrell",
    "Filmes protagonizados por Will Ferrell",
    "Movies starring Ryan Gosling",
    "Filmes protagonizados por Ryan Gosling",
]

    
    for text in test_cases:
        entities, conjunction = extractor.extract(text)
        print(f"\n'{text}'")
        if entities:
            for ent in entities:
                print(f"  - {ent['text']} ({ent['label']})")
        else:
            print("  - No entities found")
        if conjunction:
            print(f"  - Conjunction: {conjunction}")


def test_semantic_search():
    print("\n" + "="*60)
    print("Testing Semantic Search")
    print("="*60)
    
    search = SemanticSearch()
    
    movies = [
        {"id": 1, "title": "Die Hard (1988)", "text": "Die Hard (1988)"},
        {"id": 2, "title": "Die Hard 2 (1990)", "text": "Die Hard 2 (1990)"},
        {"id": 3, "title": "Die Another Day (2002)", "text": "James Bond: Die Another Day"},
        {"id": 4, "title": "Mission: Impossible (1996)", "text": "Mission: Impossible (1996)"},
    ]
    
    queries = [
        "Die Hard",
        "action movie 1988",
        "James Bond film",
    ]
    
    for query in queries:
        results, similarities = search.search(query, movies, top_k=3)
        print(f"\nQuery: '{query}'")
        for i, (result, sim) in enumerate(zip(results, similarities)):
            print(f"  {i+1}. {result['title']} (similarity: {sim:.2f})")


def test_language_detection():
    print("\n" + "="*60)
    print("Testing Language Detection")
    print("="*60)
    
    classifier = IntentClassifier()
    
    test_cases = [
        ("What movies has Tom Cruise starred in?", "en"),
        ("Qual é o filme de ficção científica mais recente?", "pt"),
        ("¿Qué películas dirigió Pedro Almodóvar?", "es"),
    ]
    
    for text, expected_lang in test_cases:
        detected_lang = classifier.detect_language(text)
        status = "✓" if detected_lang == expected_lang else "✗"
        print(f"{status} '{text[:40]}...' → {detected_lang}")


def main():
    print("\n" + "="*60)
    print("NLP Microservice - Validation Tests")
    print("="*60)
    
    try:
        test_intent_classification()
        test_multiple_intents()
        test_entity_extraction()
        test_semantic_search()
        test_language_detection()
        
        print("\n" + "="*60)
        print("✓ All tests completed successfully!")
        print("="*60 + "\n")
        
    except Exception as e:
        print(f"\n✗ Test failed with error: {e}")
        import traceback
        traceback.print_exc()
        sys.exit(1)


if __name__ == "__main__":
    main()
