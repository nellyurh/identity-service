<?php

declare(strict_types=1);

namespace Tests\Contract;

use PHPUnit\Framework\TestCase;

/**
 * Verifies the event envelope this service emits (EventBridgePublisher) satisfies the
 * platform contract published in unero-shared-schemas (required fields + declared
 * properties). The schema is read from the `schemas/shared` submodule — never duplicated
 * in-repo. Run `git submodule update --init` first (Makefile `install` does this).
 */
final class EventEnvelopeContractTest extends TestCase
{
    private const string SCHEMA = __DIR__.'/../../schemas/shared/schemas/envelopes/event-envelope.schema.json';

    public function test_publisher_envelope_matches_shared_schema(): void
    {
        if (! is_file(self::SCHEMA)) {
            $this->fail('Shared schema submodule not initialised. Run: git submodule update --init');
        }

        $schema = json_decode((string) file_get_contents(self::SCHEMA), true, flags: JSON_THROW_ON_ERROR);

        // The exact envelope shape EventBridgePublisher::relayBatch emits.
        $envelope = [
            'event_id' => '0192f1c0-0000-7000-8000-000000000000',
            'event_type' => 'UserCreated',
            'event_version' => 1,
            'emitted_at' => '2026-07-02T12:00:00+00:00',
            'producer' => 'identity-service',
            'correlation_id' => 'req_0192f1c0',
            'causation_id' => null,
            'schema_version' => '1.0.0',
            'payload' => ['user_id' => 'u-1'],
        ];

        foreach ($schema['required'] as $field) {
            $this->assertArrayHasKey($field, $envelope, "missing required envelope field: {$field}");
        }

        $allowed = array_keys($schema['properties']);
        foreach (array_keys($envelope) as $key) {
            $this->assertContains($key, $allowed, "unexpected envelope field: {$key}");
        }
    }
}
