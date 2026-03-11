SELECT
  l.id,
  l.title,
  l.media_urls,
  m.media_url as msg_media_url,
  m.raw_body
FROM listings l
LEFT JOIN messages m ON m.id = l.message_id
LIMIT 4;
