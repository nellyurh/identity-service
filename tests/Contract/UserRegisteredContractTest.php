<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Domain\Identity\User\Event\UserRegistered;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

/**
 * Verifies the UserRegistered payload this service emits satisfies the platform contract in
 * unero-shared-schemas. The schema is read from the `schemas/shared` submodule — never
 * duplicated in-repo. Requires the submodule to carry UserRegistered.schema.json
 * (Identity owns this event's vocabulary; see EVENTS.md).
 */
final class UserRegisteredContractTest extends TestCase
{
    private const string SCHEMA = __DIR__.'/../../schemas/shared/schemas/events/UserRegistered.schema.json';

    public function test_payload_matches_shared_schema(): void
    {
        if (! is_file(self::SCHEMA)) {
            $this->fail('UserRegistered.schema.json missing from shared-schemas submodule. Add it, then: git submodule update --remote schemas/shared');
        }

        $schema = json_decode((string) file_get_contents(self::SCHEMA), true, flags: JSON_THROW_ON_ERROR);

        $payload = (new UserRegistered((string) new Ulid, false, '2026-07-02T10:00:00+00:00'))->payload();

        foreach ($schema['required'] as $field) {
            $this->assertArrayHasKey($field, $payload, "missing required field: {$field}");
        }
        $allowed = array_keys($schema['properties']);
        foreach (array_keys($payload) as $key) {
            $this->assertContains($key, $allowed, "unexpected field: {$key}");
        }
        $this->assertIsBool($payload['email_verified']);
    }
}
