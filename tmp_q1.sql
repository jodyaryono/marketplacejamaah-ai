-- All contacts that are NOT completed onboarding
SELECT id, phone_number, name, onboarding_status, is_registered, member_role, sell_products, buy_products, created_at, last_seen
FROM contacts
WHERE onboarding_status IS NULL
   OR onboarding_status != 'completed'
ORDER BY id;
