-- Agent logs for images 256-266 (10 images from 6287877465688)
SELECT a.id, a.agent_name, a.message_id, a.status, LEFT(a.output_payload::text, 120) as output, a.created_at
FROM agent_logs a
WHERE a.message_id BETWEEN 256 AND 266
ORDER BY a.message_id, a.id;
