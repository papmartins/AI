#!/bin/bash
# Quick Start Script - NLP Microservice Setup
# Automatiza toda a configuração

set -e

echo "================================"
echo "NLP Microservice - Quick Start"
echo "================================"

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Step 1: Verify Python
echo -e "\n${BLUE}[1/6]${NC} Checking Python installation..."
if ! command -v python3 &> /dev/null; then
    echo -e "${YELLOW}⚠ Python 3 not found. Installing...${NC}"
    # For Ubuntu/Debian
    sudo apt-get update && sudo apt-get install -y python3 python3-pip
fi
python3 --version

# Step 2: Create virtual environment
echo -e "\n${BLUE}[2/6]${NC} Setting up Python virtual environment..."
cd ml_service
if [ ! -d "venv" ]; then
    python3 -m venv venv
    echo -e "${GREEN}✓ Virtual environment created${NC}"
else
    echo -e "${GREEN}✓ Virtual environment already exists${NC}"
fi

# Step 3: Install dependencies
echo -e "\n${BLUE}[3/6]${NC} Installing Python dependencies..."
source venv/bin/activate
pip install -q -r requirements.txt
echo -e "${GREEN}✓ Dependencies installed${NC}"

# Step 4: Download spaCy models
echo -e "\n${BLUE}[4/6]${NC} Downloading spaCy language models..."
python -m spacy download en_core_web_sm -q
python -m spacy download pt_core_news_sm -q 2>/dev/null || echo "   (Portuguese model optional)"
python -m spacy download es_core_news_sm -q 2>/dev/null || echo "   (Spanish model optional)"
echo -e "${GREEN}✓ Language models ready${NC}"

# Step 5: Create .env file
echo -e "\n${BLUE}[5/6]${NC} Checking configuration..."
if [ ! -f ".env" ]; then
    cp .env.example .env
    echo -e "${GREEN}✓ .env created from template${NC}"
else
    echo -e "${GREEN}✓ .env already configured${NC}"
fi

# Step 6: Run tests
echo -e "\n${BLUE}[6/6]${NC} Running validation tests..."
python test_microservice.py

# Success
echo -e "\n${GREEN}================================${NC}"
echo -e "${GREEN}✓ Setup Complete!${NC}"
echo -e "${GREEN}================================${NC}"

echo -e "\n${BLUE}Next steps:${NC}"
echo "1. Start microservice:"
echo -e "   ${YELLOW}python main.py${NC}"
echo ""
echo "2. In another terminal, test with:"
echo -e "   ${YELLOW}curl http://localhost:8001/health${NC}"
echo ""
echo "3. Access interactive docs:"
echo -e "   ${YELLOW}http://localhost:8001/docs${NC}"
echo ""
echo "4. Or use Docker:"
echo -e "   ${YELLOW}docker-compose up -d nlp-service${NC}"
echo -e "\n${GREEN}Documentation:${NC}"
echo "  • NLP_MICROSERVICE.md - Full architecture"
echo "  • MIGRATION_GUIDE.md - Step-by-step integration"
echo "  • BEFORE_AFTER.md - Comparison & examples"
