<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Agent logs for message 83 ===\n";
$logs = DB::table('agent_logs')->where('message_id', 83)->orderBy('id')->get();
foreach ($logs as $l) {
    echo "  [{$l->id}] {$l->agent_name} status={$l->status} output=" . substr(json_encode($l->output_payload), 0, 200) . "\n";
}

echo "\n=== Companion search test for message 83 ===\n";
$m83 = DB::table('messages')->where('id', 83)->first();
$from = date('Y-m-d H:i:s', strtotime($m83->sent_at) - 600);
$to = date('Y-m-d H:i:s', strtotime($m83->sent_at) + 600);
echo "Window: $from to $to\n";

$companion = DB::table('messages')
    ->where('whatsapp_group_id', $m83->whatsapp_group_id)
    ->where('sender_number', $m83->sender_number)
    ->where('id', '!=', 83)
    ->where('is_ad', true)
    ->where(function($q) use ($from, $to) {
        $q->where(function($inner) use ($from, $to) {
            $inner->whereNotNull('sent_at')->whereBetween('sent_at', [$from, $to]);
        })->orWhereBetween('created_at', [$from, $to]);
    })
    ->orderByDesc('sent_at')
    ->first();

if ($companion) {
    echo "Companion found: id={$companion->id} is_ad={$companion->is_ad} sent_at={$companion->sent_at}\n";
    $listing = DB::table('listings')->where('message_id', $companion->id)->first(['id','title']);
    echo "Listing: " . ($listing ? "id={$listing->id} title={$listing->title}" : "NONE") . "\n";
} else {
    echo "NO companion found!\n";
    echo "All messages from same sender+group with is_ad:\n";
    $all = DB::table('messages')
        ->where('whatsapp_group_id', $m83->whatsapp_group_id)
        ->where('sender_number', $m83->sender_number)
        ->get(['id','is_ad','sent_at','created_at','message_type']);
    foreach($all as $r) {
        echo "  id={$r->id} is_ad={$r->is_ad} type={$r->message_type} sent_at={$r->sent_at}\n";
    }
}
