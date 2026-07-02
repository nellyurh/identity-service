<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Domain\Identity\Token\Event\TokenRevoked;
use App\Domain\Identity\Token\RevocationReason;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

/**
 * Verifies the TokenRevoked payload this service emits satisfies the platform contract in
 * unero-shared-schemas. The schema is read from the `schemas/shared` submodule — never
 * duplicated in-repo. Also asserts every RevocationReason the domain can emit is a member of
 * the schema's reason enum, so the two never drift.
 */
final class TokenRevokedContractTest extends TestCase
{
    private const string SCHEMA = __DIR__.'/../../schemas/shared/schemas/events/TokenRevoked.schema.json';

    public function test_payload_matches_shared_schema(): void
    {
        if (! is_file(self::SCHEMA)) {
            $this->fail('TokenRevoked.schema.json missing from shared-schemas submodule. Add it, then: git submodule update --remote schemas/shared');
        }

        $schema = json_decode((string) file_get_contents(self::SCHEMA), true, flags: JSON_THROW_ON_ERROR);

        $payload = (new TokenRevoked((string) new Ulid, (string) new Ulid, RevocationReason::Logout->value, '2026-07-02T10:00:00+00:00'))->payload();

        foreach ($schema['required'] as $field) {
            $this->assertArrayHasKey($field, $payload, "missing required field: {$field}");
        }
        $allowed = array_keys($schema['properties']);
        foreach (array_keys($payload) as $key) {
            $this->assertContains($key, $allowed, "unexpected field: {$key}");
        }

        $enum = $schema['properties']['reason']['enum'];
        foreach (RevocationReason::cases() as $reason) {
            $this->assertContains($reason->value, $enum, "reason not in shared enum: {$reason->value}");
        }
    }
}
