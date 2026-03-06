import os
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

class Config:
    # Service configuration
    SERVICE_PORT = int(os.getenv("NLP_SERVICE_PORT", 8001))
    LOG_LEVEL = os.getenv("LOG_LEVEL", "INFO").upper()
    
    # Cache configuration
    CACHE_TTL = int(os.getenv("CACHE_TTL", 3600))
    CACHE_SIZE = int(os.getenv("CACHE_SIZE", 1000))
    
    # Model configuration
    MODEL_DIR = os.getenv("MODEL_DIR", "models")
    
    # API configuration
    MAX_REQUEST_SIZE = os.getenv("MAX_REQUEST_SIZE", "10MB")
    WORKERS = int(os.getenv("WORKERS", 4))
    
    # Language support
    SUPPORTED_LANGUAGES = ["pt", "en", "es"]
    
    # Recommendation configuration
    DEFAULT_RECOMMENDATION_LIMIT = int(os.getenv("DEFAULT_RECOMMENDATION_LIMIT", 5))
    
    # Rate limiting (to be implemented)
    RATE_LIMIT = int(os.getenv("RATE_LIMIT", 100))
    RATE_LIMIT_WINDOW = int(os.getenv("RATE_LIMIT_WINDOW", 60))

# Create config instance
config = Config()

if __name__ == "__main__":
    print("Current Configuration:")
    print(f"Service Port: {config.SERVICE_PORT}")
    print(f"Log Level: {config.LOG_LEVEL}")
    print(f"Cache TTL: {config.CACHE_TTL}")
    print(f"Supported Languages: {config.SUPPORTED_LANGUAGES}")