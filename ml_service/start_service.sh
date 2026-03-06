#!/bin/bash

# ML Microservice Startup Script

echo "Starting ML Microservice..."

# Check if Python is available
if ! command -v python3 &> /dev/null; then
    echo "Error: Python 3 is not installed"
    exit 1
fi

# Check if we're running in Docker
if [ -f /.dockerenv ]; then
    echo "Running in Docker container"
    cd /app
else
    echo "Running locally"
    # Check if virtual environment exists
    if [ -d "venv" ]; then
        echo "Activating virtual environment"
        source venv/bin/activate
    fi
fi

# Check if requirements are installed
if ! python3 -c "import fastapi, spacy" &> /dev/null; then
    echo "Installing requirements..."
    pip install -r requirements.txt
fi

# Models are loaded by the FastAPI application on startup
# No need to pre-load them here

# Start the service
echo "Starting FastAPI service..."
exec python3 src/main.py