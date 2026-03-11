#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute='
$r = App\Models\Contact::where("name","like","%egar%")->orWhere("name","like","%36%")->orWhere("name","like","%arman%")->orWhere("name","like","%PARMAN%")->get(["id","phone_number","name","address","member_role","sell_products","buy_products"]);
foreach($r as $c) echo json_encode($c->toArray())."\n";
echo "count: ".$r->count()."\n";
'
