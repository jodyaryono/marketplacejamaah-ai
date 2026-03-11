<?php
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

// Find Dwi Janto's messages
$msgs = \App\Models\Message::where('sender_name', 'like', '%Dwi Janto%')
    ->orWhere('sender_number', 'like', '%9880220%')
    ->orderBy('created_at', 'desc')
    ->take(30)
    ->get();

echo "=== DWI JANTO MESSAGES ===\n";
foreach ($msgs as $m) {
    echo "ID: {$m->id} | Cat: {$m->message_category}\n";
    echo "  Body: " . substr($m->raw_body ?? '(null)', 0, 150) . "\n";
    echo "  Time: {$m->created_at}\n";
    // Print all columns
    $cols = $m->getAttributes();
    foreach ($cols as $k => $v) {
        if (in_array($k, ['id','sender_name','sender_number','raw_body','message_category','created_at'])) continue;
        if ($v !== null && $v !== '' && $v !== false) echo "  {$k}: " . substr(json_encode($v), 0, 200) . "\n";
    }
    echo "\n";
}

// Also check listings
$listings = \App\Models\Listing::whereHas('contact', function($q) {
    $q->where('name', 'like', '%Dwi Janto%')
      ->orWhere('phone_number', 'like', '%9880220%');
})->orderBy('created_at', 'desc')->take(10)->get();

echo "=== DWI JANTO LISTINGS ===\n";
foreach ($listings as $l) {
    echo "Listing ID: {$l->id} | Title: {$l->title} | Status: {$l->status}\n";
    echo "  Media: " . json_encode($l->media_urls) . "\n";
    echo "  Created: {$l->created_at}\n\n";
}

// Check agent logs
$logs = \App\Models\AgentLog::where('input_payload', 'like', '%Dwi Janto%')
    ->orWhere('input_payload', 'like', '%9880220%')
    ->orderBy('created_at', 'desc')
    ->take(10)
    ->get(['id','agent_name','status','created_at','error']);

echo "=== AGENT LOGS ===\n";
foreach ($logs as $log) {
    echo "Log ID: {$log->id} | Agent: {$log->agent_name} | Status: {$log->status} | {$log->created_at}\n";
    if ($log->error) echo "  Error: {$log->error}\n";
}
