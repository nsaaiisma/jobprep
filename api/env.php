<?php

// Probe SEMENTARA: coba beberapa varian DSN Neon. HAPUS setelah stabil.
header('Content-Type: application/json');

$user = getenv('DB_USERNAME');
$pass = getenv('DB_PASSWORD');
$ep = 'ep-super-violet-b4odolr6';
$direct = getenv('DB_HOST');
$pooler = str_replace('.c-6.', '-pooler.c-6.', $direct);

$variants = [
    'A_direct_plain' => "pgsql:host={$direct};port=5432;dbname=neondb;sslmode=require",
    'B_direct_endpoint' => "pgsql:host={$direct};port=5432;dbname=neondb;sslmode=require;options='endpoint={$ep}'",
    'C_direct_dashc' => "pgsql:host={$direct};port=5432;dbname=neondb;sslmode=require;options='-c endpoint={$ep}'",
    'D_pooler_endpoint' => "pgsql:host={$pooler};port=5432;dbname=neondb;sslmode=require;options='endpoint={$ep}'",
    'E_pooler_plain' => "pgsql:host={$pooler};port=5432;dbname=neondb;sslmode=require",
];

$results = [];
foreach ($variants as $name => $dsn) {
    try {
        $pdo = new PDO($dsn, $user, $pass, [PDO::ATTR_TIMEOUT => 8]);
        $results[$name] = 'OK';
        $pdo = null;
    } catch (Throwable $e) {
        $results[$name] = 'FAIL: '.substr(preg_replace('/\s+/', ' ', $e->getMessage()), 0, 140);
    }
}

echo json_encode(['libpq_note' => 'see results', 'results' => $results]);
