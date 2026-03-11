<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Message 798 - the emoji from the person right before bot delete
echo "=== Message 798 full ===\n";
$m = App\Models\Message::find(798);
if ($m) {
    echo "sender_number: {$m->sender_number}\n";
    echo "sender_name: {$m->sender_name}\n";
    echo "body: {$m->raw_body}\n";
    echo "payload:\n" . json_encode($m->raw_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
}

// Message 799 - bot reply
echo "\n=== Message 799 full ===\n";
$m2 = App\Models\Message::find(799);
if ($m2) {
    echo "body: {$m2->raw_body}\n";
}

// Contact for 55650075820153
echo "\n=== Contact 55650075820153 ===\n";
$c = App\Models\Contact::where('phone_number', '55650075820153')->first();
echo $c ? "ID:{$c->id} Name:{$c->name} Phone:{$c->phone_number}" : "NOT FOUND";
echo "\n";

// Check all messages from group in last 6h
echo "\n=== All group messages last 6h ===\n";
$msgs = App\Models\Message::whereNotNull('whatsapp_group_id')
    ->where('created_at', '>', now()->subHours(6))
    ->orderByDesc('id')->take(30)->get();
foreach ($msgs as $m) {
    echo "#{$m->id} [{$m->sender_name}] ({$m->sender_number}) type={$m->message_type} body=" . substr($m->raw_body ?? '', 0, 60) . "\n";
}

// Check all contacts with LID-like numbers
echo "\n=== Recent contacts ===\n";
$contacts = App\Models\Contact::orderByDesc('id')->take(10)->get();
foreach ($contacts as $c) {
    echo "ID:{$c->id} Phone:{$c->phone_number} Name:{$c->name}\n";
}
