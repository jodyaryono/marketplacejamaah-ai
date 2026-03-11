-- Pending contacts (not completed onboarding)
SELECT id, phone_number, name, onboarding_status, is_registered, member_role, created_at
FROM contacts
WHERE onboarding_status IS NULL
   OR onboarding_status != 'completed'
   OR is_registered = false
ORDER BY id DESC
LIMIT 25;
