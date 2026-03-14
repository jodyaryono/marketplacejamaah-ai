<?php

/**
 * tmp_replay_missed.php
 * Re-injects messages that were dropped during the 401 webhook downtime
 * Period: 2026-03-13 ~15:24 – 21:14 (WEBHOOK_SECRET mismatch)
 *
 * Usage: php8.3 tmp_replay_missed.php
 */

// ─── Config ────────────────────────────────────────────────────────────────
$webhookUrl = 'https://marketplacejamaah-ai.jodyaryono.id/api/webhook/whacenter';
$secret = 'wh_mj_s3cr3t_2026x';
$phoneId = '6281317647379';
$groupJid = '6285719195627-1540340459@g.us';

// ─── Messages to replay ────────────────────────────────────────────────────
// Source: integrasi_wa.messages_log, session_id=6281317647379, direction='in'
// between 15:24:30 and 21:14:00, filtered to exclude status@broadcast

$messages = [
    // 1. WAG ad text from 6283872516619 (land for sale, Sukabumi)
    [
        'phone_id' => $phoneId,
        'message_id' => 'false_6285719195627-1540340459@g.us_ACCFD025D4A6530F9F767A9877073ACF_175514006331420@lid',
        'message' => "Di jual luas 4000 meter,lokasi di kp citugu cikakak,Sertipikat lngsung dibikinkan atas nama pembeli...view kelaut,pohon cengkeh 50,duren super umur setahun,40 pohon, musangking,bawor,matahari,Gandaria,durian hitam,mentega,pohon Klapa,pohon pisang 500 pohon,jambu,sumber air bagus,\n\nHarga 300 juta, terimakasih",
        'type' => 'conversation',
        'timestamp' => 1773399079,
        'sender' => '6283872516619',
        'sender_name' => null,
        'from' => $groupJid,
        'pushname' => null,
        'group_id' => $groupJid,
        'from_group' => $groupJid,
        'group_name' => 'Marketplace Jamaah',
        '_key' => ['remoteJid' => $groupJid, 'id' => 'false_6285719195627-1540340459@g.us_ACCFD025D4A6530F9F767A9877073ACF_175514006331420@lid', 'fromMe' => false, 'participant' => '6283872516619@s.whatsapp.net'],
    ],
    // 2. DM from master: "System health" health-check command
    [
        'phone_id' => $phoneId,
        'message_id' => 'false_91268138926223@lid_AC93436E8DB147330FBDCBF3735AA0BC',
        'message' => 'System health',
        'type' => 'conversation',
        'timestamp' => 1773399164,
        'sender' => '6285719195627',
        'sender_name' => 'jody aryono',
        'from' => '6285719195627',
        'pushname' => 'jody aryono',
        '_key' => ['remoteJid' => '91268138926223@lid', 'id' => 'false_91268138926223@lid_AC93436E8DB147330FBDCBF3735AA0BC', 'fromMe' => false],
    ],
    // 3. DM from master: "Bantuan" (help menu)
    [
        'phone_id' => $phoneId,
        'message_id' => 'false_91268138926223@lid_AC8213ACF550C26EDBB13209FED90D2E',
        'message' => 'Bantuan',
        'type' => 'conversation',
        'timestamp' => 1773399496,
        'sender' => '6285719195627',
        'sender_name' => 'jody aryono',
        'from' => '6285719195627',
        'pushname' => 'jody aryono',
        '_key' => ['remoteJid' => '91268138926223@lid', 'id' => 'false_91268138926223@lid_AC8213ACF550C26EDBB13209FED90D2E', 'fromMe' => false],
    ],
];

// ─── Replay ────────────────────────────────────────────────────────────────
$ok = 0;
$fail = 0;

foreach ($messages as $i => $payload) {
    $senderLabel = $payload['sender'];
    $msgPreview = mb_substr($payload['message'] ?? '', 0, 50);
    $isGroup = isset($payload['group_id']);

    echo '[' . ($i + 1) . '/' . count($messages) . '] ';
    echo ($isGroup ? 'WAG ' : 'DM  ') . "{$senderLabel}: \"{$msgPreview}\"...\n";

    $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ch = curl_init($webhookUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $json,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'X-Webhook-Secret: ' . $secret,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_TIMEOUT => 30,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($httpCode === 200) {
        echo "    → HTTP {$httpCode} OK\n";
        $ok++;
    } else {
        echo "    → HTTP {$httpCode} FAIL: {$err} / {$response}\n";
        $fail++;
    }

    sleep(3);  // Let the queue worker finish before the next message
}

echo "\nDone. OK={$ok} FAIL={$fail}\n";
