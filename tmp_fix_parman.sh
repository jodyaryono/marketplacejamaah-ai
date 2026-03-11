#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute='
$c = App\Models\Contact::find(18);
$c->update([
    "name" => "Parman Siregar",
    "address" => "Bogor, Tangsel, Cibubur",
    "sell_products" => "emas, mobil, property, makanan, pakaian, jasa haji",
    "buy_products" => null,
]);
echo "Updated: " . json_encode($c->only(["id","name","address","member_role","sell_products","buy_products"]));
'
