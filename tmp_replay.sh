#!/bin/bash
curl -s -X POST "http://localhost:3001/api/replay-group?token=fc42fe461f106cdee387e807b972b52b" \
  -H "Content-Type: application/json" \
  -d '{"phone_id":"6281317647379","group_id":"6285719195627-1540340459@g.us","limit":30,"sender_filter":"628119880220"}'
echo ""
