<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    public function test_liveness_is_ok(): void
    {
        $this->getJson('/healthz')
            ->assertOk()
            ->assertExactJson(['status' => 'ok']);
    }

    public function test_readiness_reports_component_checks(): void
    {
        $response = $this->getJson('/readyz');

        $this->assertContains($response->getStatusCode(), [200, 503]);
        $response->assertJsonStructure(['status', 'checks' => ['database', 'redis']]);
        // The database (sqlite :memory: in tests) is always reachable.
        $this->assertSame('up', $response->json('checks.database'));
    }
}
