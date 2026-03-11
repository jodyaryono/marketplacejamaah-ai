#!/usr/bin/env php
<?php

chdir('/var/www/marketplacejamaah-ai.jodyaryono.id');
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Delete the last 2 bot messages in Abie's DM
$response = \Illuminate\Support\Facades\Http::withHeaders([
    'Authorization' => 'Bearer fc42fe461f106cdee387e807b972b52b',
    'Content-Type' => 'application/json',
])->post('http://localhost:3001/api/delete-last-dm', [
    'phone_number' => '6289666928600',
    'count' => 2,
]);

echo 'Status: ' . $response->status() . "\n";
echo 'Body: ' . $response->body() . "\n";

$msg = "Hehe iya kak Abie, makasih apresiasinya ya! 😄👍\n\n";
$msg .= "Betul, ini memang dirancang khusus buat grup jualan — supaya iklan anggota otomatis masuk ke website marketplace kita juga, jadi jangkauannya lebih luas.\n\n";
$msg .= 'Kalau ada yang mau ditanyakan atau dibantu, langsung tanya aja kak! 🙏';

$wa = app('App\Services\WhacenterService');
$result = $wa->sendMessage('6289666928600', $msg);
echo "SENT\n";
