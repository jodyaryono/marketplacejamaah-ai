SELECT id, message_type, sender_number, LEFT(raw_body,50) as body, created_at
FROM messages
WHERE whatsapp_group_id IS NOT NULL
  AND is_ad IS NULL
  AND sender_number != 'bot'
ORDER BY id DESC
LIMIT 20;
