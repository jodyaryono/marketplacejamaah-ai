<?php
// Check gateway DB for recent messages
$dsn = "pgsql:host=localhost;port=5432;dbname=integrasi_wa";
try {
    $pdo = new PDO($dsn, 'integrasi_wa', 'integrasi_wa');
} catch (Exception $e) {
    // Try with postgres superuser
    try {
        $pdo = new PDO("pgsql:host=localhost;port=5432;dbname=integrasi_wa", 'postgres', '');
    } catch (Exception $e2) {
        echo "DB connection failed: " . $e->getMessage() . "\n";
        echo "Also tried postgres: " . $e2->getMessage() . "\n";
        // Try reading from .env
        $env = parse_ini_file('/var/www/integrasi-wa.jodyaryono.id/.env');
        echo "DB user from env: " . ($env['DB_USER'] ?? $env['PGUSER'] ?? 'not found') . "\n";
        echo "DB name from env: " . ($env['DB_NAME'] ?? $env['PGDATABASE'] ?? 'not found') . "\n";
        exit(1);
    }
}
echo "Connected to gateway DB\n";
$stmt = $pdo->query("SELECT id, session_id, direction, from_number, substring(message,1,50) as msg, media_type, status, created_at FROM messages_log WHERE created_at >= NOW() - INTERVAL '2 hours' ORDER BY id DESC LIMIT 20");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID:{$row['id']} [{$row['created_at']}] {$row['direction']} from:{$row['from_number']} type:{$row['media_type']} {$row['msg']}\n";
}
