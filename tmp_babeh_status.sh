#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute='
$c = App\Models\Contact::where("phone_number","6281413033339")->first();
echo json_encode([
    "name" => $c->name,
    "status" => $c->onboarding_status,
    "registered" => $c->is_registered,
    "role" => $c->member_role,
    "address" => $c->address,
    "sell" => $c->sell_products,
    "buy" => $c->buy_products,
], JSON_PRETTY_PRINT);
'
