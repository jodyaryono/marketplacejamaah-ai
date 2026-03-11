#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute='
$names = ["arief","wijaya","abie","faizal","faisal"];
$contacts = App\Models\Contact::where(function($q) use ($names) {
    foreach($names as $n) $q->orWhere("name","ilike","%{$n}%");
})->get(["id","phone_number","name","address","member_role","sell_products","buy_products","is_registered","onboarding_status"]);
foreach($contacts as $c) {
    echo json_encode($c->toArray(), JSON_PRETTY_PRINT)."\n---\n";
    // Check last messages
    $msgs = App\Models\Message::where(function($q) use ($c) {
        $q->where("sender_number",$c->phone_number)->whereNull("whatsapp_group_id");
    })->orWhere(function($q) use ($c) {
        $q->where("sender_number","bot")->where("recipient_number",$c->phone_number);
    })->orderBy("created_at","desc")->limit(5)->get(["sender_number","raw_body","created_at"])->reverse();
    foreach($msgs as $m) {
        $who = $m->sender_number === "bot" ? "BOT" : "USER";
        echo "  [{$m->created_at}] {$who}: ".mb_substr($m->raw_body ?? "",0,80)."\n";
    }
    echo "===\n";
}
echo "Found: ".$contacts->count()."\n";
'
