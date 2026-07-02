<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Domain\Identity\ServiceAccount\Event\ServiceAccountCreated;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ServiceAccountCreatedContractTest extends TestCase
{
    private const string SCHEMA = __DIR__.'/../../schemas/shared/schemas/events/ServiceAccountCreated.schema.json';

    public function test_payload_matches_shared_schema(): void
    {
        if (! is_file(self::SCHEMA)) {
            $this->fail('ServiceAccountCreated.schema.json missing from shared-schemas submodule.');
        }

        $schema = json_decode((string) file_get_contents(self::SCHEMA), true, flags: JSON_THROW_ON_ERROR);
        $payload = (new ServiceAccountCreated((string) new Ulid, 'wallet', ['wallet.credit'], '2026-07-02T10:00:00+00:00'))->payload();

        foreach ($schema['required'] as $field) {
            $this->assertArrayHasKey($field, $payload, "missing required field: {$field}");
        }
        foreach (array_keys($payload) as $key) {
            $this->assertContains($key, array_keys($schema['properties']), "unexpected field: {$key}");
        }
        $this->assertArrayNotHasKey('secret', $payload);
    }
}
