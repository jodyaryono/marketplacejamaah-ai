#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute='
$c = App\Models\Contact::where("name","like","%jody%")->orWhere("name","like","%Jody%")->first();
if($c) echo "Jody: ".$c->phone_number."\n";
echo "---\n";
$r = App\Models\Contact::where("name","like","%Regar%")->orWhere("name","like","%R 36%")->orWhere("name","like","%Parman%")->get(["id","phone_number","name","address","member_role","sell_products","buy_products"]);
foreach($r as $c) echo json_encode($c->toArray())."\n";
'
