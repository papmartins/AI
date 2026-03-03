"""
NLP Microservice for Intent Classification, Entity Extraction, and Semantic Search
Supports: Portuguese, English, Spanish
"""

from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from typing import List, Dict, Optional
import numpy as np
import logging
from loguru import logger
import os
from dotenv import load_dotenv

# compatibility patch for torch/pytree issue (register_pytree_node missing)
try:
    import torch
    if not hasattr(torch.utils._pytree, 'register_pytree_node') and hasattr(torch.utils._pytree, '_register_pytree_node'):
        torch.utils._pytree.register_pytree_node = torch.utils._pytree._register_pytree_node
except ImportError:
    pass

from services.intent_classifier import IntentClassifier
from services.entity_extractor import EntityExtractor
from services.semantic_search import SemanticSearch
from typing import Optional

# Load env
load_dotenv()

app = FastAPI(title="NLP Microservice", version="1.0.0")

# CORS
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

# Initialize services
intent_classifier = IntentClassifier()
entity_extractor = EntityExtractor()
semantic_search = SemanticSearch()

# Logger
logging.basicConfig(level=logging.INFO)
logger = logging.getLogger(__name__)


# Models
class IntentRequest(BaseModel):
    question: str
    language: Optional[str] = None


class IntentResponse(BaseModel):
    intent: str
    confidence: float
    language: str


class MultiIntentRequest(BaseModel):
    question: str
    language: Optional[str] = None


class MultiIntentResponse(BaseModel):
    intents: List[str]
    confidences: List[float]
    condition: str = "AND"
    language: str
    conjunction: Optional[str] = None


class EntityExtractionRequest(BaseModel):
    text: str
    language: Optional[str] = None


class EntityExtractionResponse(BaseModel):
    entities: List[Dict]
    language: str
    conjunction: Optional[str] = None


class SemanticSearchRequest(BaseModel):
    query: str
    items: List[Dict]
    language: Optional[str] = None
    top_k: int = 5


class SemanticSearchResponse(BaseModel):
    results: List[Dict]
    similarities: List[float]

class ChatbotRequest(BaseModel):
    question: str
    language: Optional[str] = None
    user_id: Optional[str] = None

class ChatbotResponse(BaseModel):
    question: str
    response: str
    language: str
    entities: List[Dict]
    intents: List[str]
    intent_condition: str
    movie_suggestions: List[Dict]
    similarities: List[float]


# Endpoints

@app.get("/health")
async def health():
    """Health check"""
    return {"status": "ok", "service": "NLP Microservice"}


@app.post("/classify-intent", response_model=IntentResponse)
async def classify_intent(request: IntentRequest):
    """
    Classify a single intent from a question
    Uses zero-shot + transformer classification
    """
    try:
        result = intent_classifier.classify(request.question, request.language)
        return IntentResponse(
            intent=result["intent"],
            confidence=result["confidence"],
            language=result["language"]
        )
    except Exception as e:
        logger.error(f"Error classifying intent: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/classify-multiple-intents", response_model=MultiIntentResponse)
async def classify_multiple_intents(request: MultiIntentRequest):
    """
    Classify multiple compound intents from a question
    E.g., actor + genre, director + year, etc.
    """
    try:
        # Use automatic language detection instead of the provided language
        result = intent_classifier.classify_multiple(request.question, None)  # None forces auto-detection
        return MultiIntentResponse(
            intents=result["intents"],
            confidences=result["confidences"],
            condition=result["condition"],
            language=result["language"],
            conjunction=result.get("conjunction")
        )
    except Exception as e:
        logger.error(f"Error classifying multiple intents: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/extract-entities", response_model=EntityExtractionResponse)
async def extract_entities(request: EntityExtractionRequest):
    """
    Extract named entities from text
    Entities: PERSON, GPE, MOVIE_TITLE, DATE, etc.
    """
    try:
        # Use automatic language detection instead of the provided language
        detected_language = entity_extractor.detect_language(request.text)
        entities, conjunction_used = entity_extractor.extract(request.text, detected_language)
        return EntityExtractionResponse(
            entities=entities if entities else [],
            language=detected_language,
            conjunction=conjunction_used
        )
    except Exception as e:
        logger.error(f"Error extracting entities: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/semantic-search", response_model=SemanticSearchResponse)
async def semantic_search_endpoint(request: SemanticSearchRequest):
    """
    Semantic search: find most similar items based on query
    E.g., search for movie titles by semantic similarity instead of exact match
    """
    try:
        results, similarities = semantic_search.search(
            query=request.query,
            items=request.items,
            language=request.language,
            top_k=request.top_k
        )
        return SemanticSearchResponse(
            results=results,
            similarities=similarities.tolist() if isinstance(similarities, np.ndarray) else similarities
        )
    except Exception as e:
        logger.error(f"Error in semantic search: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


@app.post("/embed-text")
async def embed_text(request: dict):
    """
    Generate embeddings for a text
    Returns vector representation for semantic matching
    """
    try:
        text = request.get("text", "")
        language = request.get("language")
        
        embedding = semantic_search.embed_text(text, language)
        return {
            "embedding": embedding.tolist() if isinstance(embedding, np.ndarray) else embedding,
            "dimension": len(embedding)
        }
    except Exception as e:
        logger.error(f"Error embedding text: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))

@app.post("/detect-language")
async def detect_language(request: dict):
    """Detect language of text"""
    try:
        text = request.get("text", "")
        language = intent_classifier.detect_language(text)
        return {"language": language, "text": text[:100]}
    except Exception as e:
        logger.error(f"Error detecting language: {str(e)}")
        raise HTTPException(status_code=500, detail=str(e))


if __name__ == "__main__":
    import uvicorn
    port = int(os.getenv("NLP_SERVICE_PORT", 8001))
    uvicorn.run(app, host="0.0.0.0", port=port)
