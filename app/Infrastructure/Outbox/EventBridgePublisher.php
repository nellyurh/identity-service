<?php

declare(strict_types=1);

namespace App\Infrastructure\Outbox;

use App\Infrastructure\Persistence\Model\OutboxEntryModel;
use Aws\EventBridge\EventBridgeClient;
use Symfony\Component\Uid\Uuid;

/**
 * Reads unpublished outbox rows and puts them on the platform EventBridge bus wrapped in
 * the standard event envelope (unero-shared-schemas). Marks each published on success.
 * Idempotent by event_id at the consumer; at-least-once here.
 */
final readonly class EventBridgePublisher
{
    public function __construct(
        private EventBridgeClient $client,
        private string $busName,
    ) {}

    public function relayBatch(int $limit = 100): int
    {
        $rows = OutboxEntryModel::query()
            ->whereNull('published_at')
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $published = 0;
        foreach ($rows as $row) {
            $envelope = [
                'event_id' => (string) Uuid::v7(),
                'event_type' => $row->event_type,
                'event_version' => $row->event_version,
                'emitted_at' => now()->toImmutable()->format(DATE_RFC3339),
                'producer' => 'identity-service',
                'correlation_id' => $row->correlation_id ?: (string) Uuid::v7(),
                'causation_id' => $row->causation_id,
                'schema_version' => $row->schema_version,
                'payload' => $row->payload_json,
            ];

            $this->client->putEvents([
                'Entries' => [[
                    'EventBusName' => $this->busName,
                    'Source' => 'unero.identity-service',
                    'DetailType' => $row->event_type,
                    'Detail' => json_encode($envelope, JSON_THROW_ON_ERROR),
                ]],
            ]);

            $row->update(['published_at' => now()->toImmutable()]);
            $published++;
        }

        return $published;
    }
}
