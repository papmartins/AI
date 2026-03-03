"""Semantic Search Service with graceful fallback when embeddings/FAISS missing."""

import logging
from typing import List, Dict, Optional, Tuple

logger = logging.getLogger(__name__)

# Attempt to import heavy ML libs; if unavailable, provide a simple fallback.
HAS_ML_SEARCH = True
try:
    import numpy as np
    from sentence_transformers import SentenceTransformer
    import faiss
except Exception as e:
    logger.warning(f"Semantic search ML libs not available: {e}. Using fallback text-similarity.")
    HAS_ML_SEARCH = False


class SemanticSearch:
    """Semantic search using embeddings + FAISS when available, otherwise fallback."""

    def __init__(self):
        self.items_cache = []
        # Instance-level flag to avoid using 'global' inside methods
        self.has_ml_search = HAS_ML_SEARCH
        if self.has_ml_search:
            try:
                self.model = SentenceTransformer('paraphrase-multilingual-MiniLM-L12-v2')
            except Exception as e:
                logger.warning(f"Failed to load SentenceTransformer: {e}. Disabling ML search.")
                self.has_ml_search = False

    def embed_text(self, text: str, language: Optional[str] = None):
        """Generate embedding for a single text or None for fallback."""
        if getattr(self, "has_ml_search", False):
            try:
                return self.model.encode(text, convert_to_numpy=True)
            except Exception as e:
                logger.error(f"Embedding failed: {e}")
                return None
        return None

    def _string_similarity(self, a: str, b: str) -> float:
        """Fallback similarity: Jaccard over word sets."""
        set_a = set(a.lower().split())
        set_b = set(b.lower().split())
        if not set_a or not set_b:
            return 0.0
        inter = set_a.intersection(set_b)
        union = set_a.union(set_b)
        return len(inter) / len(union)

    def search(
        self,
        query: str,
        items: List[Dict],
        language: Optional[str] = None,
        top_k: int = 5
    ) -> Tuple[List[Dict], List[float]]:
        """
        Semantic search over a list of items. Returns (results, similarities).
        If embeddings/FAISS are available uses them; otherwise uses simple string similarity.
        """
        if not items:
            return [], []

        texts = [item.get('text') or item.get('title') or str(item) for item in items]

        if getattr(self, "has_ml_search", False):
            try:
                embeddings = self.model.encode(texts, convert_to_numpy=True)
                query_emb = self.model.encode(query, convert_to_numpy=True)
                dim = embeddings.shape[1]
                index = faiss.IndexFlatL2(dim)
                index.add(embeddings.astype(np.float32))
                distances, indices = index.search(np.array([query_emb], dtype=np.float32), min(top_k, len(items)))
                similarities = (1.0 / (1.0 + distances[0])).tolist()
                # order by similarity
                order = sorted(range(len(similarities)), key=lambda i: -similarities[i])
                results = [items[i] for i in order]
                sims = [similarities[i] for i in order]
                return results[:top_k], sims[:top_k]
            except Exception as e:
                logger.error(f"Semantic search (ML) failed: {e}")

        # Fallback: simple string similarity
        scored = []
        for item, text in zip(items, texts):
            sim = self._string_similarity(query, text)
            scored.append((item, sim))

        scored.sort(key=lambda x: x[1], reverse=True)
        results = [s[0] for s in scored[:top_k]]
        sims = [float(s[1]) for s in scored[:top_k]]
        return results, sims

    def search_by_similarity(self, query: str, items: List[str], top_k: int = 5) -> List[Tuple[str, float]]:
        items_dicts = [{'text': item} for item in items]
        results, similarities = self.search(query, items_dicts, top_k=top_k)
        return [(r.get('text') or r.get('title') or str(r), float(sim)) for r, sim in zip(results, similarities)]

    def find_closest_movie_title(self, query: str, movie_titles: List[str]) -> Tuple[Optional[str], float]:
        if not movie_titles:
            return None, 0.0
        results = self.search_by_similarity(query, movie_titles, top_k=1)
        if results:
            return results[0][0], results[0][1]
        return None, 0.0
