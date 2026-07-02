<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Application\Port\AuditWriter;

/** Captures audit calls for assertion in application-service tests. */
final class RecordingAuditWriter implements AuditWriter
{
    /** @var list<array<string,mixed>> */
    public array $records = [];

    public function record(
        string $action,
        string $actorId,
        string $target,
        array $before,
        array $after,
        string $requestId,
        ?string $reason,
    ): void {
        $this->records[] = ['action' => $action, 'actorId' => $actorId, 'target' => $target, 'before' => $before, 'after' => $after, 'requestId' => $requestId, 'reason' => $reason];
    }

    public function actions(): array
    {
        return array_map(static fn (array $r): string => $r['action'], $this->records);
    }
}
