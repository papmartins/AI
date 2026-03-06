#!/usr/bin/env python3

import sys
import os
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

# Load spaCy models first
import spacy
models = {}
models['en'] = spacy.load("en_core_web_sm")

# Monkey patch the models into the main module
import src.main
src.main.models = models

from src.main import classify_intents

# Test multiple questions
test_questions = [
    "Who is the director of The Dark Knight?",
    "Who directed Mad Max?",
    "Movies starring Bruce Willis",
    "Movies directed by Christopher Nolan",
    "Who starred in Inception?"
]

for question in test_questions:
    result = classify_intents(question, 'en')
    print(f"Question: {question}")
    print(f"  Intents: {result['intents']}")
    print(f"  Entities: {result['entities']}")
    print()