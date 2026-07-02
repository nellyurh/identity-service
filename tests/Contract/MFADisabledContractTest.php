<?php

declare(strict_types=1);

namespace Tests\Contract;

use App\Domain\Identity\Mfa\Event\MFADisabled;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class MFADisabledContractTest extends TestCase
{
    private const string SCHEMA = __DIR__.'/../../schemas/shared/schemas/events/MFADisabled.schema.json';

    public function test_payload_matches_shared_schema(): void
    {
        if (! is_file(self::SCHEMA)) {
            $this->fail('MFADisabled.schema.json missing from shared-schemas submodule.');
        }

        $schema = json_decode((string) file_get_contents(self::SCHEMA), true, flags: JSON_THROW_ON_ERROR);
        $payload = (new MFADisabled((string) new Ulid, 'totp', '2026-07-02T10:00:00+00:00'))->payload();

        foreach ($schema['required'] as $field) {
            $this->assertArrayHasKey($field, $payload, "missing required field: {$field}");
        }
        foreach (array_keys($payload) as $key) {
            $this->assertContains($key, array_keys($schema['properties']), "unexpected field: {$key}");
        }
    }
}
