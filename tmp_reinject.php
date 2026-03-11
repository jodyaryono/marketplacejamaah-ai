<?php
chdir('/var/www/marketplacejamaah-ai.jodyaryono.id');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$payload = [
    'phone_id'    => '6281317647379',
    'message_id'  => 'false_6285719195627-1540340459@g.us_A5C160EF001869F511B85A60CFF3D56F_130292211818591@lid_manual',
    'message'     => "Bismillah HR ini ada arem arem isi ayam wortel kentang dan risol rogurd ayam Monggo siap pesan",
    'type'        => 'image',
    'timestamp'   => 1773014907,
    'sender'      => '130292211818591',
    'sender_name' => 'W.A.S',
    'from'        => '6285719195627-1540340459',
    'pushname'    => 'W.A.S',
    'group_id'    => '6285719195627-1540340459@g.us',
    'from_group'  => '6285719195627-1540340459@g.us',
    'group_name'  => 'Marketplace Jamaah',
    '_key'        => [
        'remoteJid'   => '6285719195627-1540340459@g.us',
        'id'          => 'false_6285719195627-1540340459@g.us_A5C160EF001869F511B85A60CFF3D56F_130292211818591@lid_manual',
        'fromMe'      => false,
        'participant' => '130292211818591@lid',
    ],
];
$listener = app(App\Agents\WhatsAppListenerAgent::class);
$msg = $listener->handle($payload);
if ($msg) {
    App\Jobs\ProcessMessageJob::dispatch($msg->id)->onQueue('agents');
    echo 'OK dispatched message_id=' . $msg->id . "\n";
} else {
    echo 'FAILED: listener returned null' . "\n";
}
