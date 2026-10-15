<?php

namespace Tests\Unit;

use App\Database\NeonPostgresConnector;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class NeonPostgresConnectorTest extends TestCase
{
    private function dsn(array $config): string
    {
        $method = new ReflectionMethod(NeonPostgresConnector::class, 'getDsn');

        return $method->invoke(new NeonPostgresConnector, array_merge([
            'host' => 'ep-test.c-6.aws.neon.tech',
            'port' => '5432',
            'database' => 'neondb',
        ], $config));
    }

    public function test_dsn_includes_raw_endpoint_option_when_configured(): void
    {
        $dsn = $this->dsn(['neon_endpoint' => 'ep-super-violet-b4odolr6']);

        $this->assertStringContainsString("options='endpoint=ep-super-violet-b4odolr6'", $dsn);
        $this->assertStringNotContainsString('-c endpoint', $dsn);
    }

    public function test_dsn_is_unchanged_without_endpoint(): void
    {
        $dsn = $this->dsn([]);

        $this->assertStringNotContainsString('options=', $dsn);
        $this->assertStringContainsString('host=ep-test.c-6.aws.neon.tech', $dsn);
    }

    public function test_dsn_rejects_malicious_endpoint(): void
    {
        $dsn = $this->dsn(['neon_endpoint' => "x';select 1;--"]);

        $this->assertStringNotContainsString('options=', $dsn);
    }
}
