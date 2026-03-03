# NLP Microservice - Python

Advanced NLP service for intent classification, entity extraction, and semantic search. Powers the Laravel chatbot with transformer-based models.

## 🚀 Quick Start

### Option 1: Automated Setup (Recommended)

```bash
bash quickstart.sh
python main.py
```

### Option 2: Manual Setup

```bash
# Create virtual environment
python3 -m venv venv
source venv/bin/activate  # or `venv\Scripts\activate` on Windows

# Install dependencies
pip install -r requirements.txt

# Download language models
python -m spacy download en_core_web_sm pt_core_news_sm es_core_news_sm

# Run
python main.py
```

### Option 3: Docker

```bash
docker-compose up nlp-service
# or
docker build -t nlp-service . && docker run -p 8001:8001 nlp-service
```

## 📚 API Reference

All endpoints return JSON. Full interactive docs at `http://localhost:8001/docs`

### Health Check
```bash
GET /health
# Response: {"status": "ok", "service": "NLP Microservice"}
```

### Classify Intent
```bash
POST /classify-intent
Body: {"question": "What movies has Tom Cruise starred in?"}
# Response: {"intent": "actor", "confidence": 0.92, "language": "en"}
```

### Classify Multiple Intents
```bash
POST /classify-multiple-intents
Body: {"question": "Show me action movies with Tom Cruise from 2020"}
# Response: {
#   "intents": ["actor", "genre", "year"],
#   "confidences": [0.85, 0.80, 0.75],
#   "condition": "AND",
#   "language": "en"
# }
```

### Extract Entities
```bash
POST /extract-entities
Body: {"text": "Tom Cruise directed by J.J. Abrams", "language": "en"}
# Response: {
#   "entities": [
#     {"text": "Tom Cruise", "label": "PERSON", "start": 0, "end": 9},
#     {"text": "J.J. Abrams", "label": "PERSON", "start": 23, "end": 34}
#   ]
# }
```

### Semantic Search
```bash
POST /semantic-search
Body: {
  "query": "Die Hard",
  "items": [
    {"title": "Die Hard (1988)"},
    {"title": "Die Hard 2 (1990)"}
  ],
  "top_k": 5
}
# Response: {
#   "results": [{"title": "Die Hard (1988)"}, ...],
#   "similarities": [0.98, 0.95, ...]
# }
```

### Detect Language
```bash
POST /detect-language
Body: {"text": "Qual é o melhor filme de ficção científica?"}
# Response: {"language": "pt"}
```

### Generate Embeddings
```bash
POST /embed-text
Body: {"text": "Tom Cruise action movie"}
# Response: {"embedding": [0.12, -0.45, ...], "dimension": 384}
```

## 📘 Beginner's Note

If you're new to NLP or Python microservices, here's what you should know:

* **Setup** – follow the Quick Start above. You only need Python 3.11+ and Docker if you don't want to install the models locally.
* **No DIY training** – the models are downloaded via `requirements.txt` and `sentence-transformers`; you will never see a `fit()` call in the code. To customise, either add rules or retrain offline.
* **Logs** – service logs are printed to `stdout`; when running under Docker `docker-compose logs nlp-service` shows them.
* **Endpoints** – read through the API Reference; try them with `curl` or the interactive Swagger UI at `/docs`.
* **Extending intents** – edit the `intent_templates` dictionary in `services/intent_classifier.py` and rebuild. For serious improvements you can export the dataset from logs and fine-tune with Hugging Face.
* **Extending NER** – spaCy models are used; to add languages install another model and add a branch in `EntityExtractor.__init__`.
* **Customizing language detection** – indicator words live in `ml_service/lang/{pt,es,en}.json`. You can edit those files or add new language JSONs; the service will pick them up on restart.

## 🧪 Tests

Run test suite:
```bash
python test_microservice.py
or
python3 test_microservice.py
```

Expected output:
```
============================================================
NLP Microservice - Validation Tests
============================================================

Testing Intent Classification
✓ 'What movies has Tom Cruise starred in?' → actor (confidence: 0.92)
✓ 'Show me horror movies from 2020' → genre (confidence: 0.88)
...

✓ All tests completed successfully!
```

## 🏗️ Architecture & How It Works

This microservice is *not* a training platform. All machine‑learning models used by the API are pre‑trained transformer models that are downloaded when the Docker image is built or on first run. The service does **not** perform any learning online; instead we rely on two mechanisms:

1. **Zero‑shot inference** – a multilingual BART model that can match arbitrary questions to a fixed set of intent labels.
2. **Rule‑based templates** – simple keyword templates defined in `intent_classifier.py` used as a fallback or for coverage beyond the model.

> 👉 If you want to improve accuracy you either
> - add new template tokens in `intent_templates` and re‑deploy, or
> - fine‑tune the Hugging Face models offline (see the FAQ section below).

The architecture consists of three logical components:

### Services

**IntentClassifier** (`services/intent_classifier.py`)
- Zero-shot classification (BART)
- Multi-intent detection
- Language detection
- Template fallback

**EntityExtractor** (`services/entity_extractor.py`)
- Named Entity Recognition (spaCy)
- Movie title extraction
- Year/date extraction
- Person name extraction

**SemanticSearch** (`services/semantic_search.py`)
- FAISS indexing
- Semantic similarity matching
- Movie title matching
- Typo tolerance

The FastAPI application in `main.py` exposes each capability over HTTP. The Laravel frontend calls these endpoints via `IntentClassifierService_Enhanced.php` and falls back to `FallbackIntentClassifier.php` if the Python service is unreachable.

**EntityExtractor** (`services/entity_extractor.py`)
- Named Entity Recognition (spaCy)
- Movie title extraction
- Year/date extraction
- Person name extraction

**SemanticSearch** (`services/semantic_search.py`)
- FAISS indexing
- Semantic similarity matching
- Movie title matching
- Typo tolerance

### Models

| Model | Purpose | Size | Languages |
|-------|---------|------|-----------|
| BART-large-mnli | Zero-shot intent | ~400MB | Multilingual |
| Sentence-BERT | Embeddings | ~100MB | 50+ languages |
| spaCy | NER | ~40MB each | en, pt, es |
| XLM-R | Language detection | ~300MB | 100+ languages |

## 📊 Performance

- Intent classification: 50-100ms (first run), <1ms (cached)
- Entity extraction: 50-200ms
- Semantic search: 50-150ms (for 1000 items)
- Language detection: 10-20ms

## 🔧 Configuration

Create `.env` file:
```env
NLP_SERVICE_PORT=8001
FLASK_ENV=production
LOG_LEVEL=INFO
```

## 📦 Dependencies

Key packages:
- `fastapi` - Web framework
- `sentence-transformers` - Embeddings
- `spacy` - NLP/NER
- `torch` - ML framework
- `transformers` - Hugging Face models
- `faiss-cpu` - Vector search

For details, see `requirements.txt`

## 🐛 Troubleshooting

### "ModuleNotFoundError: No module named 'spacy'"
```bash
pip install -r requirements.txt
python -m spacy download en_core_web_sm
```

### "CUDA out of memory"
Use CPU-only version:
```bash
pip install torch --index-url https://download.pytorch.org/whl/cpu
```

### "Connection refused" from Laravel
Check service is running:
```bash
curl http://localhost:8001/health
```

### Slow first request
Models load on first request (~3s). Normal. Cache second request.

## 📈 Monitoring

Check service health programmatically:
```python
import requests
response = requests.get("http://localhost:8001/health")
print(response.json())
```

Or from bash:
```bash
curl -s http://localhost:8001/health | jq .
```

## 🚀 Production Tips

1. **Caching**: Use Redis for distributed cache
2. **Load balancing**: Run multiple instances behind nginx
3. **GPU**: Use GPU-enabled image for 10x speedup
4. **Monitoring**: Export Prometheus metrics
5. **Quantization**: Use ONNX for smaller models
6. **Async**: All endpoints support concurrent requests

## 📚 Documentation

- [Main Documentation](../docs/NLP_MICROSERVICE.md)
- [Migration Guide](../docs/MIGRATION_GUIDE.md)
- [Before/After Comparison](../docs/BEFORE_AFTER.md)
- [Implementation Summary](../docs/IMPLEMENTATION_SUMMARY.md)

## 🤝 Integration

Used by Laravel chatbot via:
```
IntentClassifierService_Enhanced
  ↓
HTTP requests (Guzzle)
  ↓
FastAPI endpoints
```

With automatic fallback to local rules if service unavailable.

## 📝 License

Part of the main Laravel application. See root LICENSE.

## 🆘 Support

1. Check docs first
2. Run `python test_microservice.py`
3. Check logs: `docker logs nlp_microservice`
4. Verify health: `curl http://localhost:8001/health`

---

**Status:** ✅ Production Ready  
**Last Updated:** March 2, 2026  
**Maintainer:** AI Lab
