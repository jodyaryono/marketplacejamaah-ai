#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute='
$all = App\Models\Contact::orderBy("name")->get(["id","phone_number","name","is_registered","onboarding_status"]);
foreach($all as $c) {
    $status = $c->is_registered ? "REG" : ($c->onboarding_status ?? "NEW");
    echo "{$c->id} | {$c->phone_number} | {$c->name} | {$status}\n";
}
echo "Total: ".$all->count()."\n";
'
