FROM python:3.11-slim

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \
    gcc \
    && rm -rf /var/lib/apt/lists/*

# Copy requirements
COPY requirements.txt .

# Install Python dependencies
RUN pip install --no-cache-dir -r requirements.txt

# Download spaCy models via pip to avoid download URL issues
RUN python -m pip install -U pip
RUN python -m pip install en_core_web_sm || true
RUN python -m pip install pt_core_news_sm || true
RUN python -m pip install es_core_news_sm || true

# (could still fallback to spacy download commands if needed)
#RUN python -m spacy download en_core_web_sm
#RUN python -m spacy download pt_core_news_sm 2>/dev/null || true
#RUN python -m spacy download es_core_news_sm 2>/dev/null || true

# Copy application
COPY . .

# Create .env if not exists
RUN if [ ! -f .env ]; then cp .env.example .env; fi

EXPOSE 8001

CMD ["python", "main.py"]
