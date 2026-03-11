#!/bin/bash
cd /var/www/marketplacejamaah-ai.jodyaryono.id
php artisan tinker --execute='
$msgs = App\Models\Message::where(function($q) {
    $q->where("sender_number","6281413033339")->whereNull("whatsapp_group_id");
})->orWhere(function($q) {
    $q->where("sender_number","bot")->where("recipient_number","6281413033339");
})->orderBy("created_at","desc")->limit(15)->get(["id","sender_number","direction","message_type","raw_body","created_at"])->reverse();
foreach($msgs as $m) {
    $who = $m->sender_number === "bot" ? "BOT" : "USER";
    $body = mb_substr($m->raw_body ?? "[{$m->message_type}]", 0, 80);
    echo "[{$m->created_at}] {$who}: {$body}\n";
}
'
