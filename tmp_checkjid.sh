#!/bin/bash
psql -U marketjam -d marketplacejamaah -c "SELECT id, sender_number, sender_jid, message_type FROM messages WHERE sender_number IN ('113335764791545','93793663627297') ORDER BY id DESC LIMIT 6;"
