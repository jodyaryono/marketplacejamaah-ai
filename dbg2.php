<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Check latest messages in group
echo "=== Group messages last 3h ===\n";
$msgs = App\Models\Message::whereNotNull('whatsapp_group_id')
    ->where('created_at', '>', now()->subHours(3))
    ->orderByDesc('id')->take(15)->get();
foreach ($msgs as $m) {
    echo "#{$m->id} [{$m->sender_name}] ({$m->sender_number}) type={$m->message_type} grp={$m->whatsapp_group_id} body=" . substr($m->raw_body ?? '', 0, 80) . "\n";
}

// Check contacts with "ana" or "ibu" in name
echo "\n=== All contacts with ana/ibu ===\n";
$contacts = App\Models\Contact::where('name', 'ILIKE', '%ana%')
    ->orWhere('name', 'ILIKE', '%ibu%')
    ->get();
foreach ($contacts as $c) {
    echo "ID:{$c->id} Phone:{$c->phone_number} Name:{$c->name} warn={$c->warning_count}\n";
}

// Look for messages from ibu Ana - search by sender_name
echo "\n=== Messages from sender_name containing 'ana'/'ibu' last 24h ===\n";
$msgs2 = App\Models\Message::where('created_at', '>', now()->subHours(24))
    ->where(function($q) {
        $q->where('sender_name', 'ILIKE', '%ana%')
          ->orWhere('sender_name', 'ILIKE', '%ibu%');
    })
    ->orderByDesc('id')->take(10)->get();
foreach ($msgs2 as $m) {
    echo "#{$m->id} [{$m->sender_name}] ({$m->sender_number}) type={$m->message_type} body=" . substr($m->raw_body ?? '', 0, 80) . "\n";
}

// Check bot messages about delete
echo "\n=== Bot messages about 'hapus' ===\n";
$msgs3 = App\Models\Message::where('sender_number', 'bot')
    ->where('raw_body', 'ILIKE', '%hapus%')
    ->where('created_at', '>', now()->subHours(3))
    ->orderByDesc('id')->take(10)->get();
foreach ($msgs3 as $m) {
    echo "#{$m->id} to={$m->sender_name} body=" . substr($m->raw_body ?? '', 0, 120) . "\n";
}
