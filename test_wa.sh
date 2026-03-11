#!/bin/bash
curl -s -X POST http://localhost:3001/api/send \
  -H 'Content-Type: application/json' \
  -H 'Authorization: Bearer fc42fe461f106cdee387e807b972b52b' \
  -d '{"phone":"6285719195627","message":"test dari server"}'
echo ""
