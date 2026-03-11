<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== All messages after 15:35 today ===\n";
$msgs = DB::table('messages')
    ->where('created_at', '>', '2026-03-08 15:30:00')
    ->orderBy('created_at')
    ->get();

foreach ($msgs as $m) {
    $body = substr($m->raw_body ?? '', 0, 80);
    echo "  id={$m->id} type={$m->message_type} from={$m->sender_number} is_ad={$m->is_ad} "
       . "is_proc={$m->is_processed} cat={$m->message_category} media=" . ($m->media_url ? 'YES' : 'NULL')
       . " created={$m->created_at}\n"
       . "    body: $body\n";
}

echo "\n=== Agent logs for these messages ===\n";
$ids = DB::table('messages')->where('created_at', '>', '2026-03-08 15:30:00')->pluck('id');
$logs = DB::table('agent_logs')->whereIn('message_id', $ids)->orderBy('id')->get();
foreach ($logs as $l) {
    $out = substr(json_encode($l->output_payload), 0, 200);
    echo "  msg={$l->message_id} agent={$l->agent_name} status={$l->status} output=$out\n";
}
