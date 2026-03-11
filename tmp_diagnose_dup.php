<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== syn🥰 (93793663627297) messages ===\n";
$msgs = DB::table('messages')->where('sender_number','93793663627297')->orderBy('id')->get(['id','message_type','raw_body','sent_at','is_ad','message_category']);
foreach ($msgs as $m) {
    echo "  id={$m->id} type={$m->message_type} is_ad={$m->is_ad} cat={$m->message_category} body=" . substr($m->raw_body ?? '', 0, 60) . "\n";
}

echo "\n=== Suryo (6281213338417) messages ===\n";
$msgs2 = DB::table('messages')->where('sender_number','6281213338417')->orderBy('id')->get(['id','message_type','raw_body','sent_at','is_ad','message_category','sender_name']);
foreach ($msgs2 as $m) {
    echo "  id={$m->id} type={$m->message_type} is_ad={$m->is_ad} cat={$m->message_category} name={$m->sender_name} body=" . substr($m->raw_body ?? '', 0, 60) . "\n";
}

echo "\n=== All contacts with 'suryo' or 'syn' in name ===\n";
$contacts = DB::table('contacts')->whereRaw("LOWER(name) LIKE '%suryo%' OR LOWER(name) LIKE '%syn%'")->get();
foreach ($contacts as $c) {
    echo "  id={$c->id} phone={$c->phone_number} name={$c->name} registered={$c->is_registered}\n";
}

echo "\n=== Number format analysis ===\n";
$allContacts = DB::table('contacts')->get(['id','phone_number','name']);
$lid = $standard = $other = 0;
foreach ($allContacts as $c) {
    if (strlen($c->phone_number) > 15) { $lid++; echo "  LONG: {$c->phone_number} ({$c->name})\n"; }
    elseif (str_starts_with($c->phone_number, '62')) { $standard++; }
    else { $other++; echo "  NON-62: {$c->phone_number} ({$c->name})\n"; }
}
echo "Standard (62xxx): $standard, LID (>15chars): $lid, Other: $other\n";
