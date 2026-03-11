-- Latest 30 group messages (most recent first)
SELECT id, message_type, is_processed, is_ad, ad_confidence,
       sender_number, LEFT(raw_body,40) as body,
       media_url IS NOT NULL as has_media,
       message_category,
       created_at
FROM messages
WHERE whatsapp_group_id IS NOT NULL
ORDER BY id DESC
LIMIT 30;
