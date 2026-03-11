#!/bin/bash
# Check QR code availability
curl -s "http://localhost:3001/api/qr/6281317647379?token=fc42fe461f106cdee387e807b972b52b" | head -100
echo ""
echo "=== Status ==="
curl -s "http://localhost:3001/api/status?token=fc42fe461f106cdee387e807b972b52b"
echo ""
