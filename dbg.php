<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$contacts = App\Models\Contact::where('name', 'ILIKE', '%ana%')->get();
foreach ($contacts as $c) {
    echo "{$c->id}|{$c->phone_number}|{$c->name}|warn={$c->warning_count}|blocked={$c->is_blocked}\n";
}
echo "---\n";
$msgs = App\Models\Message::where('created_at', '>', now()->subHours(3))->orderByDesc('id')->take(15)->get();
foreach ($msgs as $m) {
    echo "#{$m->id} [{$m->sender_name}] ({$m->sender_number}) type={$m->message_type} " . substr($m->raw_body ?? '', 0, 50) . "\n";
}
echo "---\n";
$logs = App\Models\AgentLog::where('created_at', '>', now()->subHours(3))->orderByDesc('id')->take(20)->get();
foreach ($logs as $l) {
    echo "#{$l->id} {$l->agent_name} msg={$l->message_id} {$l->status}\n";
}
