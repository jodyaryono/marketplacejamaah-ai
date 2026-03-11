#!/bin/bash
# Delete Raup's status mention message from the group
curl -s -X POST "http://localhost:3001/api/delete?token=fc42fe461f106cdee387e807b972b52b" \
  -H "Content-Type: application/json" \
  -d '{"phone_id":"6281317647379","key":{"remoteJid":"6285719195627-1540340459@g.us","id":"false_6285719195627-1540340459@g.us_ACB8CF9A1BA42336E0E9DD926C65CB82_81338409447592@lid","fromMe":false,"participant":"81338409447592@lid"}}'
echo ""
