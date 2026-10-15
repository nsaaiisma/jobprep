<?php

// Probe SEMENTARA (tanpa Laravel) untuk cek env + koneksi di runtime Vercel. HAPUS setelah stabil.
header('Content-Type: application/json');

$lengths = [];
foreach (['APP_KEY', 'APP_URL', 'DB_CONNECTION', 'DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_USERNAME', 'DB_PASSWORD', 'DB_SSLMODE', 'GEMINI_API_KEY'] as $k) {
    $v = getenv($k);
    $lengths[$k] = is_string($v) ? strlen($v) : null;
}

$db = 'SKIP';
try {
    $dsn = 'pgsql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT').';dbname='.getenv('DB_DATABASE').';sslmode='.getenv('DB_SSLMODE');
    $pdo = new PDO($dsn, getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_TIMEOUT => 8]);
    $db = 'OK '.$pdo->query('select version()')->fetchColumn();
} catch (Throwable $e) {
    $db = 'FAIL '.get_class($e).': '.substr($e->getMessage(), 0, 160);
}

$egress = 'SKIP';
try {
    $ctx = stream_context_create(['http' => ['method' => 'GET', 'timeout' => 10, 'ignore_errors' => true]]);
    $r = @file_get_contents('https://generativelanguage.googleapis.com/', false, $ctx);
    $egress = ($r !== false || isset($http_response_header)) ? 'OK '.substr($http_response_header[0] ?? '', 0, 60) : 'FAIL no-response';
} catch (Throwable $e) {
    $egress = 'FAIL '.substr($e->getMessage(), 0, 120);
}

echo json_encode([
    'php' => PHP_VERSION,
    'pdo_pgsql' => extension_loaded('pdo_pgsql'),
    'env_lengths' => $lengths,
    'db_connect' => is_string($db) && str_starts_with($db, 'OK') ? 'OK (version hidden)' : $db,
    'egress_https' => $egress,
]);
