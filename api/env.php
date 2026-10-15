<?php

// Probe SEMENTARA (tanpa Laravel) untuk cek env + ekstensi PHP di runtime Vercel. HAPUS setelah stabil.
header('Content-Type: application/json');

echo json_encode([
    'php' => PHP_VERSION,
    'pdo_pgsql' => extension_loaded('pdo_pgsql'),
    'pgsql' => extension_loaded('pgsql'),
    'sqlite' => extension_loaded('pdo_sqlite'),
    'APP_ENV' => getenv('APP_ENV'),
    'APP_DEBUG' => getenv('APP_DEBUG'),
    'DB_CONNECTION' => getenv('DB_CONNECTION'),
    'DB_HOST' => getenv('DB_HOST'),
    'DB_DATABASE' => getenv('DB_DATABASE'),
    'DB_USERNAME' => getenv('DB_USERNAME'),
    'DB_SSLMODE' => getenv('DB_SSLMODE'),
    'GEMINI_MODEL' => getenv('GEMINI_MODEL'),
    'VIEW_COMPILED_PATH' => getenv('VIEW_COMPILED_PATH'),
    'tmp_views_writable' => is_writable('/tmp') ? 'yes' : 'no',
]);
