-- Recent messages from LID senders (14+ digit numbers)
SELECT id, message_type, is_processed, is_ad, ad_confidence, sender_number, LEFT(raw_body,50) as body, media_url IS NOT NULL as has_media, created_at
FROM messages
WHERE sender_number ~ '^\d{14,}$'
  AND whatsapp_group_id IS NOT NULL
ORDER BY id DESC
LIMIT 15;
