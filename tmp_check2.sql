SELECT a.id, a.agent_name, a.message_id, a.status, a.output_payload, a.created_at
FROM agent_logs a
WHERE a.message_id IN (285, 253, 56, 52)
ORDER BY a.message_id, a.id;
