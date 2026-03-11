<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$phone = '113335764791545';
$contact = DB::table('contacts')->where('phone_number', $phone)->first();
echo "Contact for $phone:\n";
if ($contact) {
    echo "  id={$contact->id} name={$contact->name} is_registered={$contact->is_registered} is_blocked={$contact->is_blocked}\n";
} else {
    echo "  NOT FOUND\n";
}

echo "\nMessages from $phone (last 10):\n";
$msgs = DB::table('messages')
    ->where('sender_number', $phone)
    ->orderByDesc('created_at')
    ->limit(10)
    ->get(['id','created_at','message_type','is_ad','is_processed','message_category','raw_body']);
foreach($msgs as $m) {
    $body = substr($m->raw_body ?? '', 0, 50);
    echo "  id={$m->id} type={$m->message_type} is_ad={$m->is_ad} is_processed={$m->is_processed} category={$m->message_category} text={$body}\n";
}

echo "\nListings from contact:\n";
$listings = DB::table('listings')
    ->join('contacts', 'listings.contact_id', '=', 'contacts.id')
    ->where('contacts.phone_number', $phone)
    ->orderByDesc('listings.created_at')
    ->limit(5)
    ->get(['listings.id','listings.title','listings.status','listings.created_at']);
foreach($listings as $l) {
    echo "  id={$l->id} status={$l->status} title={$l->title}\n";
}
if (count($listings) === 0) echo "  (none)\n";
