<?php
require '/var/www/marketplacejamaah-ai.jodyaryono.id/vendor/autoload.php';
$app = require_once '/var/www/marketplacejamaah-ai.jodyaryono.id/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// All messages from Sharif (6281584739110)
$msgs = \App\Models\Message::where('sender_number', '6281584739110')
    ->orderBy('sent_at','desc')
    ->take(15)
    ->get(['id','message_type','raw_body','is_ad','media_url','sent_at','message_category','whatsapp_group_id']);

echo "--- All messages from Sharif (6281584739110) ---\n";
foreach($msgs as $m) {
    echo "#{$m->id} | {$m->message_type} | is_ad=".($m->is_ad===null?'NULL':($m->is_ad?'Y':'N'))." | cat={$m->message_category} | media=".($m->media_url ?: 'none')." | grp={$m->whatsapp_group_id} | ".substr($m->raw_body ?? '',0,80)." | {$m->sent_at}\n";
}

// Check the uploads directory for video files
echo "\n--- Video files in uploads ---\n";
$dir = '/var/www/integrasi-wa.jodyaryono.id/uploads/';
if (is_dir($dir)) {
    foreach (glob($dir . '*.{mp4,webm,3gp,avi,mov}', GLOB_BRACE) as $f) {
        echo basename($f) . ' (' . round(filesize($f)/1024) . " KB)\n";
    }
}

// Check all video type messages
echo "\n--- All video messages in system ---\n";
$vids = \App\Models\Message::where('message_type', 'video')
    ->orderBy('sent_at','desc')
    ->take(10)
    ->get(['id','sender_number','sender_name','media_url','sent_at','is_ad','message_category']);
foreach($vids as $v) {
    echo "#{$v->id} | {$v->sender_name} ({$v->sender_number}) | media=".($v->media_url ?: 'none')." | is_ad=".($v->is_ad===null?'NULL':($v->is_ad?'Y':'N'))." | {$v->sent_at}\n";
}
