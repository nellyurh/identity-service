<?php

declare(strict_types=1);

namespace App\Infrastructure\Outbox;

use App\Infrastructure\Persistence\Model\OutboxEntryModel;
use Illuminate\Support\Facades\Context;
use Symfony\Component\Uid\Ulid;

/**
 * Writes an outbox row inside the current (business) transaction. The row is the durable
 * intent to publish; the relay publishes it and marks it published. Never publishes to
 * EventBridge directly from the transaction path.
 */
final class OutboxWriter
{
    /** @param array<string,mixed> $payload */
    public function write(
        string $eventType,
        int $eventVersion,
        string $schemaVersion,
        string $aggregateType,
        string $aggregateId,
        array $payload,
    ): void {
        OutboxEntryModel::query()->create([
            'id' => (string) new Ulid,
            'event_type' => $eventType,
            'event_version' => $eventVersion,
            'schema_version' => $schemaVersion,
            'aggregate_type' => $aggregateType,
            'aggregate_id' => $aggregateId,
            'payload_json' => $payload,
            'correlation_id' => (string) Context::get('correlation_id', ''),
            'causation_id' => Context::get('causation_id'),
            'created_at' => now()->toImmutable(),
            'published_at' => null,
        ]);
    }
}
