#!/bin/bash
# Clean up duplicate LID contact for master (jody aryono)
# Contact 51 (LID 91268138926223) → merge into Contact 8 (real phone 6285719195627)

echo "=== Before cleanup ==="
psql -U postgres -d marketplacejamaah -c "SELECT id, phone_number, name, is_registered, message_count FROM contacts WHERE id IN (8, 51);"

echo ""
echo "=== Updating messages from LID to real phone ==="
psql -U postgres -d marketplacejamaah -c "UPDATE messages SET sender_number = '6285719195627' WHERE sender_number = '91268138926223';"

echo ""
echo "=== Deleting duplicate LID contact 51 ==="
psql -U postgres -d marketplacejamaah -c "DELETE FROM contacts WHERE id = 51 AND phone_number = '91268138926223';"

echo ""
echo "=== After cleanup ==="
psql -U postgres -d marketplacejamaah -c "SELECT id, phone_number, name, is_registered, message_count FROM contacts WHERE id IN (8, 51);"
psql -U postgres -d marketplacejamaah -c "SELECT COUNT(*) as msgs_from_master FROM messages WHERE sender_number = '6285719195627';"
