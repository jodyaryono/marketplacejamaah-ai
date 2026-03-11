<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check Mirakusuma contact
$contact = \App\Models\Contact::where('phone_number', 'LIKE', '%87843201618%')->first();
if ($contact) {
    echo "Contact: ".json_encode($contact->only(['id','phone_number','name','is_registered','onboarding_status','member_role','sell_products']), JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)."\n\n";
} else {
    echo "Contact NOT FOUND for 87843201618\n\n";
}

// Check messages from this sender
$msgs = \App\Models\Message::where('sender_number', 'LIKE', '%87843201618%')
    ->orderBy('sent_at','desc')
    ->take(10)
    ->get(['id','message_type','raw_body','is_ad','is_processed','message_category','media_url','sent_at','sender_number','sender_name','whatsapp_group_id','raw_payload']);

echo "--- Messages ---\n";
foreach($msgs as $m) {
    echo "#{$m->id} | sender={$m->sender_number} | name={$m->sender_name} | {$m->message_type} | proc=".($m->is_processed?'Y':'N')." | is_ad=".($m->is_ad===null?'NULL':($m->is_ad?'Y':'N'))." | cat={$m->message_category} | media=".($m->media_url?'YES':'no')." | grp={$m->whatsapp_group_id} | ".substr($m->raw_body ?? '',0,100)." | {$m->sent_at}\n";
    // Check raw_payload for WA info
    $payload = $m->raw_payload;
    if ($payload) {
        echo "  payload keys: ".implode(', ', array_keys($payload))."\n";
        if (isset($payload['pushName'])) echo "  pushName: {$payload['pushName']}\n";
        if (isset($payload['sender_name'])) echo "  sender_name: {$payload['sender_name']}\n";
        if (isset($payload['notifyName'])) echo "  notifyName: {$payload['notifyName']}\n";
        if (isset($payload['vcard'])) echo "  vcard: ".substr($payload['vcard'],0,200)."\n";
    }
}

// Check listings
$listings = \App\Models\Listing::whereHas('message', fn($q) => $q->where('sender_number', 'LIKE', '%87843201618%'))->get();
echo "\n--- Listings ---\n";
foreach($listings as $l) {
    echo "Listing #{$l->id} | {$l->title} | status={$l->status} | media=".json_encode($l->media_urls)."\n";
}
