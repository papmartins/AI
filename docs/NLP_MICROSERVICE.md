# NLP Microservice Architecture

## Overview

A robust, scalable NLP system for intent classification, entity extraction, and semantic search. Replaces keyword-based rules with transformer-based deep learning models while maintaining fast fallback capabilities.

## Architecture

```
┌─────────────────┐
│   Laravel App   │
│   (PHP)         │
└────────┬────────┘
         │ HTTP
         │ (Guzzle)
         ↓
┌──────────────────────┐
│  NLP Microservice    │
│  (Python/FastAPI)    │
├──────────────────────┤
│ • Intent Classifier  │
│ • Entity Extractor   │
│ • Semantic Search    │
│ • Language Detection │
└──────────────────────┘
```

## Key Improvements Over Original

| Feature | Original | Enhanced |
|---------|----------|----------|
| Intent Detection | KNN + Keywords | Zero-shot + Transformers |
| Entity Recognition | Regex + String matching | Transformer-based NER (spaCy) |
| Movie Title Search | Exact match only | Semantic similarity search |
| Unknown Titles | Fails silently | Finds best match |
| Multilingual | Limited | Full pt/en/es support |
| Fallback | None | Local rule-based system |
| Performance | Fast but limited | Smart caching layer |

## Installation

### 1. Setup Python Microservice

```bash
cd ml_service

# Create virtual environment
python -m venv venv
source venv/bin/activate  # or `venv\Scripts\activate` on Windows

# Install dependencies
pip install -r requirements.txt

# Download spaCy models
python -m spacy download en_core_web_sm
python -m spacy download pt_core_news_sm
python -m spacy download es_core_news_sm
```

### 2. Configure Laravel

```bash
# Copy environment config
cp .env.nlp.example .env

# Update .env with microservice URL
NLP_SERVICE_URL=http://localhost:8001
```

### 3. Run with Docker Compose

```bash
docker-compose up -d
```

This will start:
- Laravel app on `http://localhost:8080`
- NLP service on `http://localhost:8001`
- MySQL on `localhost:3306`
- Node dev server on `http://localhost:5173`

## API Endpoints

### Classification

**POST /classify-intent**
```json
{
  "question": "What movies has Tom Cruise starred in?"
}
```

**POST /classify-multiple-intents**
```json
{
  "question": "Show me action movies from 2020 with Tom Cruise"
}
```

### Entity Extraction

**POST /extract-entities**
```json
{
  "text": "Tom Cruise directed by Christopher McQuarrie",
  "language": "en"
}
```

Response:
```json
{
  "entities": [
    {"text": "Tom Cruise", "label": "PERSON"},
    {"text": "Christopher McQuarrie", "label": "PERSON"}
  ]
}
```

### Semantic Search

**POST /semantic-search**
```json
{
  "query": "Die Hard",
  "items": [
    {"id": 1, "title": "Die Hard (1988)"},
    {"id": 2, "title": "Die Hard 2 (1990)"}
  ],
  "top_k": 5
}
```

Response includes similarity scores (0-1).

## Integration in Laravel

### Using Enhanced Services

Replace in your controller:

```php
// Old
$classifier = new IntentClassifierService();

// New - Enhanced with ML microservice
$classifier = new IntentClassifierService();
$chatbot = new NLPChatbotServiceEnhanced();

// Automatic fallback if service unavailable
$response = $chatbot->answer("Movies with 'Die Hard' in the title");
```

### Caching

Responses are cached for 1 hour to reduce microservice calls:
```php
NLP_CACHE_EXPIRY=3600
```

## Fallback Behavior

If NLP microservice is unavailable:
1. Request times out after 10s
2. Logs warning message
3. Falls back to local rule-based classifier
4. Returns reasonable result using keyword patterns

No user-facing errors!

## Models Used

- **Intent Classification**: Facebook BART (zero-shot, multilingual)
- **Entity Recognition**: spaCy transformers (multilingual)
- **Embeddings**: Sentence-BERT MiniLM (multilingual)
- **Search**: FAISS (efficient similarity search)

All models automatically download on first run.

## Performance

- Intent classification: ~50-100ms (with cache hit: <1ms)
- Entity extraction: ~100-200ms
- Semantic search: ~50-150ms per 1000 items
- Fallback (no ML): <5ms

## Customization

### Add Custom Intents

Edit `ml_service/services/intent_classifier.py`:

```python
self.intent_labels = [
    "actor", "director", "genre", "year", "rating", 
    "title", "recommendation", "YOUR_NEW_INTENT"
]
```

### Improve Accuracy

Add training samples in `resources/lang/{lang}/chatbot/training_samples.php`

### Fine-tune Models

Replace transformer models in microservice for domain-specific accuracy.

## Monitoring

Check service health:

```php
if ($classifier->isServiceHealthy()) {
    // Use ML service
} else {
    // Using fallback
}
```

View logs:
```bash
docker logs nlp_microservice
```

## Troubleshooting

**Service won't start**
```bash
# Check logs
docker logs nlp_microservice

# Verify Python environment
python --version  # Should be 3.11+
```

**Slow requests**
- Enable Redis caching
- Reduce batch size
- Use smaller models (DistilBERT instead of BERT)

**Out of memory**
- Model files are ~800MB
- Ensure 4GB+ available
- Use GPU-accelerated version for production

## Production Deployment

For production:

1. Use GPU-enabled Docker image
2. Enable model quantization (ONNX)
3. Set up Redis cache
4. Use load balancer (nginx) for multiple instances
5. Monitor with Prometheus/Grafana
6. Implement rate limiting
7. Use API keys for microservice access

## Testing

```python
cd ml_service
pytest tests/

# Or directly
python -c "
from services.intent_classifier import IntentClassifier
ic = IntentClassifier()
result = ic.classify('Movies with Tom Cruise')
print(result)
"
```

## Documentation

- [FastAPI Docs](http://localhost:8001/docs) - Interactive API documentation
- [Sentence-BERT](https://www.sbert.net/) - Embedding models
- [spaCy NER](https://spacy.io/usage/named-entities) - Entity recognition
- [FAISS](https://github.com/facebookresearch/faiss) - Semantic search

## Next Steps

1. Deploy NLP service (Docker/Kubernetes)
2. Set up monitoring and alerts
3. Collect user queries for continuous improvement
4. Fine-tune models with domain-specific data
5. Implement active learning for edge cases
