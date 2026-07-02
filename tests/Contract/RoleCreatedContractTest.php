<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Domain\Identity\Role\Event\RoleCreated;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

/**
 * Verifies the RoleCreated payload this service emits satisfies the platform contract in
 * unero-shared-schemas. The schema is read from the `schemas/shared` submodule.
 */
final class RoleCreatedContractTest extends TestCase
{
    private const string SCHEMA = __DIR__.'/../../schemas/shared/schemas/events/RoleCreated.schema.json';

    public function test_payload_matches_shared_schema(): void
    {
        if (! is_file(self::SCHEMA)) {
            $this->fail('RoleCreated.schema.json missing from shared-schemas submodule. Add it, then: git submodule update --remote schemas/shared');
        }

        $schema = json_decode((string) file_get_contents(self::SCHEMA), true, flags: JSON_THROW_ON_ERROR);

        $payload = (new RoleCreated((string) new Ulid, 'member', false, '2026-07-02T10:00:00+00:00'))->payload();

        foreach ($schema['required'] as $field) {
            $this->assertArrayHasKey($field, $payload, "missing required field: {$field}");
        }
        $allowed = array_keys($schema['properties']);
        foreach (array_keys($payload) as $key) {
            $this->assertContains($key, $allowed, "unexpected field: {$key}");
        }
        $this->assertIsBool($payload['is_system']);
    }
}
