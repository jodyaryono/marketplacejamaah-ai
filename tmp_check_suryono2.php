<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Find Suryono's contact
$contact = \App\Models\Contact::where('phone_number', '6281213338417')->first();
echo "Contact: id={$contact->id} name={$contact->name}\n\n";

// Check messages by sender_number
$msgs = \App\Models\Message::where('sender_number', '6281213338417')
    ->orderBy('sent_at','desc')
    ->take(20)
    ->get(['id','message_type','raw_body','is_ad','is_processed','message_category','media_url','sent_at','whatsapp_group_id']);

echo "--- Messages from 6281213338417 ---\n";
foreach($msgs as $m) {
    echo "#{$m->id} | {$m->message_type} | proc=".($m->is_processed?'Y':'N')." | is_ad=".($m->is_ad===null?'NULL':($m->is_ad?'Y':'N'))." | cat={$m->message_category} | media=".($m->media_url?'YES':'no')." | grp={$m->whatsapp_group_id} | ".substr($m->raw_body ?? '',0,80)." | {$m->sent_at}\n";
}

// Also check by old LID number
$msgs2 = \App\Models\Message::where('sender_number', '93793663627297')
    ->orderBy('sent_at','desc')
    ->take(10)
    ->get(['id','message_type','raw_body','is_ad','is_processed','message_category','media_url','sent_at','sender_number']);

echo "\n--- Messages from LID 93793663627297 ---\n";
foreach($msgs2 as $m) {
    echo "#{$m->id} | sender={$m->sender_number} | {$m->message_type} | proc=".($m->is_processed?'Y':'N')." | is_ad=".($m->is_ad===null?'NULL':($m->is_ad?'Y':'N'))." | cat={$m->message_category} | media=".($m->media_url?'YES':'no')." | ".substr($m->raw_body ?? '',0,80)." | {$m->sent_at}\n";
}

// Check listings for this contact
$listings = \App\Models\Listing::where('contact_id', $contact->id)->get();
echo "\n--- Listings for contact #{$contact->id} ---\n";
foreach($listings as $l) {
    echo "Listing #{$l->id} | {$l->title} | status={$l->status} | msg_id={$l->message_id} | media=".json_encode($l->media_urls)."\n";
    echo "  desc: ".substr($l->description ?? '', 0, 200)."\n";
}

// Check latest messages in the group (to see the Generali messages)
echo "\n--- Latest 15 group messages (all senders) ---\n";
$latest = \App\Models\Message::whereNotNull('whatsapp_group_id')
    ->orderBy('sent_at','desc')
    ->take(15)
    ->get(['id','message_type','raw_body','is_ad','is_processed','message_category','media_url','sent_at','sender_number','sender_name']);
foreach($latest as $m) {
    echo "#{$m->id} | {$m->sender_name} ({$m->sender_number}) | {$m->message_type} | proc=".($m->is_processed?'Y':'N')." | is_ad=".($m->is_ad===null?'NULL':($m->is_ad?'Y':'N'))." | cat={$m->message_category} | media=".($m->media_url?'YES':'no')." | ".substr($m->raw_body ?? '',0,60)." | {$m->sent_at}\n";
}
