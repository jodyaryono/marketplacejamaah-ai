<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Message 83 details ===\n";
$m83 = DB::table('messages')->where('id', 83)->first();
echo "  raw_body: " . ($m83->raw_body ?? 'NULL') . "\n";
echo "  sent_at: " . ($m83->sent_at ?? 'NULL') . "\n";
echo "  created_at: " . $m83->created_at . "\n";
echo "  group_id: " . ($m83->whatsapp_group_id ?? 'NULL') . "\n";
echo "  sender: " . $m83->sender_number . "\n";

echo "\n=== Message 90 (ad) details ===\n";
$m90 = DB::table('messages')->where('id', 90)->first();
echo "  is_ad: " . ($m90->is_ad ?? 'NULL') . "\n";
echo "  sent_at: " . ($m90->sent_at ?? 'NULL') . "\n";
echo "  created_at: " . $m90->created_at . "\n";
echo "  group_id: " . ($m90->whatsapp_group_id ?? 'NULL') . "\n";

echo "\n=== Time diff ===\n";
if ($m83->sent_at && $m90->sent_at) {
    $diff = abs(strtotime($m83->sent_at) - strtotime($m90->sent_at));
    echo "  sent_at diff: {$diff} seconds (" . round($diff/60, 2) . " min)\n";
}
$diff2 = abs(strtotime($m83->created_at) - strtotime($m90->created_at));
echo "  created_at diff: {$diff2} seconds (" . round($diff2/60, 2) . " min)\n";

echo "\n=== Does listing exist for message 90? ===\n";
$listing = DB::table('listings')->where('message_id', 90)->first(['id','title','status']);
if ($listing) {
    echo "  listing id={$listing->id} title={$listing->title} status={$listing->status}\n";
} else {
    echo "  NO LISTING for message_id=90\n";
    // Check by contact
    $contact = DB::table('contacts')->where('id', 23)->first();
    $listing2 = DB::table('listings')->where('contact_id', 23)->first(['id','title','message_id']);
    echo "  listing via contact: " . ($listing2 ? "id={$listing2->id} title={$listing2->title} msg={$listing2->message_id}" : 'none') . "\n";
}
