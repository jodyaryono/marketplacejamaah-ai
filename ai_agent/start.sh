#!/bin/bash
# Start the Marketplace Jamaah AI Payment Agent microservice
set -e

echo "🕌 Marketplace Jamaah AI — Payment Agent"
echo "⚡ Arc Blockchain + USYC Nanopayments"
echo ""

cd "$(dirname "$0")"

# Install dependencies if needed
if [ ! -d ".venv" ]; then
    echo "📦 Setting up virtualenv..."
    python3 -m venv .venv
    .venv/bin/pip install -r requirements.txt
fi

source .venv/bin/activate

echo "🚀 Starting FastAPI on port 8001..."
uvicorn main:app --host 0.0.0.0 --port 8001 --reload
