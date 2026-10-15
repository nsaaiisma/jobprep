<?php

namespace App\Providers;

use App\Database\NeonPostgresConnector;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ganti konektor pgsql bawaan agar DSN bisa membawa endpoint ID
        // Neon (dibutuhkan libpq tanpa SNI, mis. runtime serverless).
        // Tanpa `neon_endpoint`, perilaku identik dengan konektor bawaan.
        DB::extend('pgsql', function (array $config, string $name): PostgresConnection {
            $pdo = (new NeonPostgresConnector)->connect($config);

            return new PostgresConnection(
                $pdo, $config['database'] ?? '', $config['prefix'] ?? '', $config
            );
        });
    }
}
