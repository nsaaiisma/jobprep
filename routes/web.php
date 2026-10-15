<?php

use App\Http\Controllers\{AuthController, CompanyController, CvController, GoogleController, InterviewController};
use Illuminate\Support\Facades\Route;

// Rute probe SEMENTARA untuk diagnosis produksi. HAPUS setelah deploy stabil.
Route::get('/__probe', function () {
    $out = [
        'php' => PHP_VERSION,
        'app_env' => config('app.env'),
        'app_debug' => config('app.debug'),
        'extensions' => array_values(array_filter(
            ['pdo_pgsql', 'pgsql', 'mbstring', 'openssl', 'fileinfo', 'zip', 'curl'],
            fn ($e) => extension_loaded($e)
        )),
        'db_driver' => config('database.default'),
        'db_host' => config('database.connections.pgsql.host'),
        'cache_store' => config('cache.default'),
        'session_driver' => config('session.driver'),
        'view_compiled' => config('view.compiled'),
        'checks' => [],
    ];

    $check = function (string $name, Closure $fn) use (&$out): void {
        try {
            $out['checks'][$name] = 'OK '.substr((string) $fn(), 0, 60);
        } catch (Throwable $e) {
            $out['checks'][$name] = 'FAIL '.get_class($e).': '.substr($e->getMessage(), 0, 200);
        }
    };

    $check('db_ping', fn () => Illuminate\Support\Facades\DB::select('select 1') ? 'pong' : 'empty');
    $check('sessions_table', fn () => Illuminate\Support\Facades\DB::table('sessions')->count().' rows');
    $check('cache_rw', fn () => Illuminate\Support\Facades\Cache::put('probe', 1, 60) && Illuminate\Support\Facades\Cache::get('probe') ? 'rw' : 'read-fail');
    $check('view_render', fn () => strlen(view('pages.home')->render()).' bytes');
    $check('tmp_views_writable', fn () => is_writable('/tmp/framework/views') ? 'yes' : 'no');
    $check('storage_views_writable', fn () => is_writable(storage_path('framework/views')) ? 'yes' : 'no');
    $check('packages_manifest', fn () => is_file(base_path('bootstrap/cache/packages.php')) ? 'exists' : 'missing');

    return response()->json($out);
});

Route::view('/', 'pages.home')->name('home');

// Tamu (belum login)
Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/masuk', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/daftar', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/daftar', [AuthController::class, 'register'])->middleware('throttle:6,1');

    Route::get('/auth/google', [GoogleController::class, 'redirect'])->name('google.redirect');
    Route::get('/auth/google/callback', [GoogleController::class, 'callback'])->name('google.callback');
});

// Wajib login
Route::middleware('auth')->group(function () {
    Route::post('/keluar', [AuthController::class, 'logout'])->name('logout');

    Route::get('/cv', [CvController::class, 'index'])->name('cv');
    Route::post('/cv', [CvController::class, 'analyze'])->middleware('throttle:10,1');

    Route::get('/wawancara', [InterviewController::class, 'index'])->name('interview');
    Route::post('/wawancara', [InterviewController::class, 'answer'])->middleware('throttle:20,1');
    Route::post('/wawancara/audio', [InterviewController::class, 'assistWithAudio'])
        ->middleware('throttle:12,1')
        ->name('interview.audio');

    Route::get('/perusahaan', [CompanyController::class, 'index'])->name('company');
    Route::post('/perusahaan', [CompanyController::class, 'research'])->middleware('throttle:10,1');
});
