#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute='echo json_encode(Schema::getColumnListing("contacts"));'
