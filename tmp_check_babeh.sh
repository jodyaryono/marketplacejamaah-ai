#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute='
$c = App\Models\Contact::where("phone_number","6281413033339")->first();
echo json_encode(["id"=>$c->id,"name"=>$c->name,"status"=>$c->onboarding_status,"registered"=>$c->is_registered,"role"=>$c->role,"city"=>$c->city]);
'
