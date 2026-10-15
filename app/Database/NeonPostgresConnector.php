<?php

namespace App\Database;

use Illuminate\Database\Connectors\PostgresConnector;

/**
 * Postgres connector that can pass Neon's endpoint ID to old libpq builds.
 *
 * Serverless PHP runtimes (e.g. Vercel) may ship a libpq without SNI
 * support, in which case Neon answers:
 * "Endpoint ID is not specified ... pass the endpoint ID".
 * The endpoint ID must travel as a RAW DSN option
 * (options='endpoint=<id>'); Laravel's `server_options` config always
 * prefixes `-c`, which the proxy rejects. So we append it ourselves,
 * only when `neon_endpoint` is configured (production on Vercel).
 */
class NeonPostgresConnector extends PostgresConnector
{
    protected function getDsn(array $config)
    {
        $dsn = parent::getDsn($config);

        $endpoint = $config['neon_endpoint'] ?? null;

        if (is_string($endpoint) && preg_match('/^[A-Za-z0-9-]{1,64}$/', $endpoint)) {
            $dsn .= ";options='endpoint={$endpoint}'";
        }

        return $dsn;
    }
}
