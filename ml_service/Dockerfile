# Use official Python image
FROM python:3.9-slim

# Set environment variables
ENV PYTHONDONTWRITEBYTECODE 1
ENV PYTHONUNBUFFERED 1

# Set work directory
WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    build-essential \
    curl \
    git \
    && rm -rf /var/lib/apt/lists/*

# Install Python dependencies
COPY requirements.txt ./
RUN pip install --no-cache-dir --upgrade pip && \
    pip install --no-cache-dir -r requirements.txt && \
    python -m spacy download pt_core_news_sm && \
    python -m spacy download en_core_web_sm && \
    python -m spacy download es_core_news_sm

# Copy project files
COPY src/ /app/src/
COPY config.py /app/config.py
COPY config/language/ /app/config/language/

# Create a non-root user for security
RUN useradd -m mluser && \
    chown -R mluser:mluser /app

# Switch to non-root user
USER mluser

# Expose port
EXPOSE 8001

# Command to run the application
CMD ["python", "src/main.py"]