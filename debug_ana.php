<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Find Ana contact
$contacts = \App\Models\Contact::where('name', 'ILIKE', '%ana%')
    ->orWhere('name', 'ILIKE', '%ibu%')
    ->get(['id', 'phone_number', 'name', 'warning_count', 'is_blocked']);
echo "=== Contacts matching Ana/Ibu ===\n";
foreach ($contacts as $c) {
    echo "ID:{$c->id} Phone:{$c->phone_number} Name:{$c->name} Warnings:{$c->warning_count} Blocked:{$c->is_blocked}\n";
}

// Recent messages
echo "\n=== Recent messages (last 2h) ===\n";
$msgs = \App\Models\Message::where('created_at', '>', now()->subHours(2))
    ->orderByDesc('id')
    ->take(15)
    ->get(['id', 'sender_number', 'sender_name', 'raw_body', 'message_type', 'created_at']);
foreach ($msgs as $m) {
    echo "#{$m->id} [{$m->sender_name}] ({$m->sender_number}) type={$m->message_type} at={$m->created_at} body=" . substr($m->raw_body ?? '', 0, 60) . "\n";
}

// Agent logs
echo "\n=== Agent logs (last 2h) ===\n";
$logs = \App\Models\AgentLog::where('created_at', '>', now()->subHours(2))
    ->orderByDesc('id')
    ->take(20)
    ->get(['id', 'agent_name', 'message_id', 'status', 'error', 'created_at']);
foreach ($logs as $l) {
    echo "#{$l->id} {$l->agent_name} msg={$l->message_id} status={$l->status} err=" . substr($l->error ?? '', 0, 60) . " at={$l->created_at}\n";
}
