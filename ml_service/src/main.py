#!/usr/bin/env python3

import os
from pydoc import doc
import spacy
import logging
from fastapi import FastAPI, HTTPException
from pydantic import BaseModel
from typing import List, Dict, Any, Optional
from dotenv import load_dotenv
import sys

# Add parent directory to path for config import
sys.path.append(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

# Load environment variables
load_dotenv()

# Import configuration
from config import config

# Import language detector
from src.language_detector import language_detector

# Import intent classifier
from src.intent_classifier import classify_intents

# Configure logging
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s'
)
logger = logging.getLogger(__name__)

# Initialize FastAPI app
app = FastAPI(
    title="ML Microservice API",
    description="Simplified ML microservice for NLP tasks using spaCy",
    version="2.0.0"
)

# Load spaCy models
models = {}

def load_spacy_models():
    """Load spaCy language models"""
    try:
        logger.info("Loading spaCy models...")
        
        # Portuguese model
        models['pt'] = spacy.load("pt_core_news_sm")
        logger.info("Loaded Portuguese model")
        
        # English model  
        models['en'] = spacy.load("en_core_web_sm")
        logger.info("Loaded English model")
        
        # Spanish model
        models['es'] = spacy.load("es_core_news_sm")
        logger.info("Loaded Spanish model")
            
    except Exception as e:
        logger.error(f"Error loading spaCy models: {e}")
        raise

class QuestionRequest(BaseModel):
    question: str
    language: Optional[str] = None
    user_id: Optional[str] = None

class IntentResponse(BaseModel):
    intents: List[str]
    entities: Dict[str, Any]
    language: str
    confidence: float

class EntityResponse(BaseModel):
    entities: Dict[str, Any]
    language: str

class RecommendationRequest(BaseModel):
    user_id: Optional[str] = None
    limit: int = 5
    filters: Optional[Dict[str, Any]] = None

class RecommendationResponse(BaseModel):
    recommendations: List[Dict[str, Any]]
    user_id: Optional[str] = None

def detect_language(text: str) -> str:
    """Detect language of the input text using language detector module"""
    try:
        return language_detector.detect_language(text)
    except Exception as e:
        logger.error(f"Error detecting language: {e}")
        return 'en'

@app.on_event("startup")
def startup_event():
    """Startup event to load models"""
    load_spacy_models()
    logger.info("ML Microservice started successfully")

@app.get("/")
def read_root():
    """Root endpoint"""
    return {"message": "ML Microservice API", "status": "running"}

@app.post("/classify-intent", response_model=IntentResponse)
def classify_intent_endpoint(request: QuestionRequest):
    """Classify intents from user question"""
    try:
        language = detect_language(request.question)
        logger.info(f"HTTP API - Question: {request.question}, Detected language: {language}")
        result = classify_intents(request.question, language, models, language_detector.languages)
        logger.info(f"HTTP API - Result: {result}")
        return result
    except Exception as e:
        logger.error(f"Error in classify_intent endpoint: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/extract-entities", response_model=EntityResponse)
def extract_entities_endpoint(request: QuestionRequest):
    """Extract entities from user question"""
    try:
        language = detect_language(request.question)
        result = classify_intents(request.question, language, models, language_detector.languages)
        
        return {
            'entities': result['entities'],
            'language': result['language']
        }
    except Exception as e:
        logger.error(f"Error in extract_entities endpoint: {e}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/recommend", response_model=RecommendationResponse)
def recommend_endpoint(request: RecommendationRequest):
    """Get movie recommendations"""
    try:
        # Mock recommendations - in production this would connect to recommendation system
        recommendations = [
            {
                'title': 'The Shawshank Redemption',
                'year': 1994,
                'genre': 'Drama',
                'rating': 4.8,
                'director': 'Frank Darabont'
            },
            {
                'title': 'The Godfather',
                'year': 1972,
                'genre': 'Crime',
                'rating': 4.7,
                'director': 'Francis Ford Coppola'
            }
        ]
        
        return {
            'recommendations': recommendations,
            'user_id': request.user_id
        }
    except Exception as e:
        logger.error(f"Error in recommend endpoint: {e}")
        raise HTTPException(status_code=500, detail=str(e))

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(
        app,
        host="0.0.0.0",
        port=8001,
        log_level="info"
    )