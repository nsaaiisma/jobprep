<?php

/**
 * Vercel serverless entry point.
 *
 * Vercel's filesystem is read-only except /tmp, so make sure the
 * directories Laravel needs at runtime exist there. The matching
 * VIEW_COMPILED_PATH / APP_*_CACHE env vars live in vercel.json.
 */
foreach (['framework/sessions', 'framework/views', 'framework/cache', 'logs', 'bootstrap-cache'] as $dir) {
    if (! is_dir('/tmp/'.$dir)) {
        mkdir('/tmp/'.$dir, 0777, true);
    }
}

require __DIR__.'/../public/index.php';
