#!/bin/bash
# ============================================================
# deploy-hackathon.sh
# Marketplace Jamaah AI — Hackathon USYC/Arc Deploy Script
# Run this ON THE VPS from the project root:
#   bash deploy-hackathon.sh
# ============================================================

set -e  # stop on any error

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}"
echo "============================================"
echo " 🕌 Marketplace Jamaah AI — Hackathon Deploy"
echo "============================================"
echo -e "${NC}"

# ── 1. Check we're in the right directory ──────────────────
if [ ! -f "artisan" ]; then
  echo -e "${RED}❌ ERROR: artisan not found. Run from Laravel project root.${NC}"
  exit 1
fi
echo -e "${GREEN}✅ Laravel project root confirmed${NC}"

# ── 2. Check .env has Arc vars ────────────────────────────
echo ""
echo -e "${YELLOW}📋 Checking .env for Arc config...${NC}"

missing=0
for var in ARC_RPC_URL ARC_OPERATOR_ADDRESS ARC_OPERATOR_PK ARC_USYC_CONTRACT ARC_TESTNET GEMINI_API_KEY; do
  if grep -q "^${var}=" .env 2>/dev/null; then
    echo -e "  ${GREEN}✓ ${var}${NC}"
  else
    echo -e "  ${RED}✗ ${var} — MISSING in .env${NC}"
    missing=1
  fi
done

if [ $missing -eq 1 ]; then
  echo ""
  echo -e "${YELLOW}⚠️  Tambahkan ke .env kamu:${NC}"
  cat << 'EOF'

ARC_RPC_URL=https://rpc.arc-testnet.io
ARC_OPERATOR_ADDRESS=0x_your_operator_address
ARC_OPERATOR_PK=your_private_key_here
ARC_USYC_CONTRACT=0x_usyc_contract_address
ARC_TESTNET=true
AI_AGENT_URL=http://127.0.0.1:8001

EOF
  echo -e "${RED}❌ Isi .env dulu, lalu jalankan ulang script ini.${NC}"
  exit 1
fi
echo -e "${GREEN}✅ Semua Arc env vars tersedia${NC}"

# ── 3. Run database migrations ────────────────────────────
echo ""
echo -e "${YELLOW}🗄️  Running migrations...${NC}"
php artisan migrate --force
echo -e "${GREEN}✅ Migrations selesai${NC}"

# ── 4. Clear & cache config ───────────────────────────────
echo ""
echo -e "${YELLOW}⚙️  Clearing & caching config...${NC}"
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
echo -e "${GREEN}✅ Config & routes cached${NC}"

# ── 5. Python AI Agent setup ──────────────────────────────
echo ""
echo -e "${YELLOW}🐍 Setting up Python AI Agent...${NC}"

if [ ! -d "ai_agent" ]; then
  echo -e "${RED}❌ Folder ai_agent/ tidak ditemukan${NC}"
  exit 1
fi

cd ai_agent

# Install deps
echo "Installing Python dependencies..."
pip install -r requirements.txt -q

# Auto-create ai_agent/.env jika belum ada
if [ ! -f ".env" ]; then
  echo -e "${YELLOW}Membuat ai_agent/.env dari parent .env...${NC}"
  LARAVEL_URL=$(grep "^APP_URL=" ../.env | cut -d'=' -f2)
  GEMINI_KEY=$(grep "^GEMINI_API_KEY=" ../.env | cut -d'=' -f2)
  ARC_RPC=$(grep "^ARC_RPC_URL=" ../.env | cut -d'=' -f2)

  cat > .env << ENVEOF
LARAVEL_API_URL=${LARAVEL_URL}
LARAVEL_API_TOKEN=
GEMINI_API_KEY=${GEMINI_KEY}
ARC_RPC_URL=${ARC_RPC}
ARC_TESTNET=true
PORT=8001
ENVEOF

  echo -e "${YELLOW}⚠️  Isi LARAVEL_API_TOKEN di ai_agent/.env jika diperlukan${NC}"
fi

cd ..

# ── 6. Start / Restart Python agent ──────────────────────
echo ""
echo -e "${YELLOW}🚀 Starting Python AI Agent on port 8001...${NC}"

# Kill existing instance
if pgrep -f "uvicorn main:app" > /dev/null 2>&1; then
  echo "Stopping existing uvicorn process..."
  pkill -f "uvicorn main:app" || true
  sleep 2
fi

# Start with nohup
PROJECT_ROOT=$(pwd)
nohup bash -c "cd ${PROJECT_ROOT}/ai_agent && uvicorn main:app --host 0.0.0.0 --port 8001" \
  > /tmp/ai_agent.log 2>&1 &

echo "Waiting for agent to start..."
sleep 4

if pgrep -f "uvicorn main:app" > /dev/null 2>&1; then
  echo -e "${GREEN}✅ Python AI Agent running (PID: $(pgrep -f 'uvicorn main:app'))${NC}"
else
  echo -e "${RED}❌ AI Agent gagal start. Cek log:${NC}"
  tail -20 /tmp/ai_agent.log
  exit 1
fi

# ── 7. Health checks ──────────────────────────────────────
echo ""
echo -e "${YELLOW}🏥 Health checks...${NC}"

APP_URL=$(grep "^APP_URL=" .env | cut -d'=' -f2)

# Laravel
HTTP=$(curl -s -o /dev/null -w "%{http_code}" "${APP_URL}/api/usyc/stats" 2>/dev/null || echo "000")
if [ "$HTTP" = "200" ] || [ "$HTTP" = "401" ]; then
  echo -e "  ${GREEN}✓ Laravel API OK (HTTP ${HTTP})${NC}"
else
  echo -e "  ${YELLOW}⚠️  Laravel API HTTP ${HTTP} — cek APP_URL di .env${NC}"
fi

# Python agent
PY=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8001/" 2>/dev/null || echo "000")
if [ "$PY" = "200" ]; then
  echo -e "  ${GREEN}✓ Python AI Agent OK (HTTP 200)${NC}"
else
  echo -e "  ${YELLOW}⚠️  Python agent HTTP ${PY} — cek /tmp/ai_agent.log${NC}"
fi

# ── 8. Test: create a demo wallet ─────────────────────────
echo ""
echo -e "${YELLOW}🧪 Quick test: create demo wallet...${NC}"
WALLET_RESP=$(curl -s -X GET "${APP_URL}/api/usyc/wallet/628123456789" \
  -H "Accept: application/json" 2>/dev/null | head -c 200 || echo "failed")
echo "  Response: ${WALLET_RESP}"

# ── 9. Summary ────────────────────────────────────────────
echo ""
echo -e "${BLUE}============================================${NC}"
echo -e "${GREEN} 🎉 Deploy Selesai!${NC}"
echo -e "${BLUE}============================================${NC}"
echo ""
echo -e "  🌐 Platform   : ${APP_URL}"
echo -e "  🎮 Demo page  : ${APP_URL}/hackathon-demo.html"
echo -e "  🤖 AI Agent   : http://127.0.0.1:8001"
echo -e "  📋 Agent logs : tail -f /tmp/ai_agent.log"
echo ""
echo -e "${YELLOW}Next:${NC}"
echo "  1. Buka ${APP_URL}/hackathon-demo.html di browser"
echo "  2. Test: ketik 'daftar wallet' di WhatsApp"
echo "  3. Record video demo pakai DEMO_SCRIPT.md"
echo "  4. Submit di lablab.ai sebelum 26 April 2026"
echo ""
echo -e "${GREEN}Barakallahu fiikum! 🕌${NC}"
echo ""
