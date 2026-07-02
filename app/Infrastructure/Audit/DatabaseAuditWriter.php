<?php

declare(strict_types=1);

namespace App\Infrastructure\Audit;

use App\Application\Port\AuditWriter;
use App\Infrastructure\Persistence\Model\AuditEventModel;
use Symfony\Component\Uid\Ulid;

/**
 * Append-only audit writer. Writes the row before the response returns; the table has no
 * UPDATE/DELETE path in application code and is guarded at the database layer.
 */
final class DatabaseAuditWriter implements AuditWriter
{
    public function record(
        string $action,
        string $actorId,
        string $target,
        array $before,
        array $after,
        string $requestId,
        ?string $reason,
    ): void {
        AuditEventModel::query()->create([
            'id' => (string) new Ulid,
            'action' => $action,
            'actor_id' => $actorId,
            'target' => $target,
            'before_json' => $before,
            'after_json' => $after,
            'request_id' => $requestId,
            'reason' => $reason,
            'created_at' => now()->toImmutable(),
        ]);
    }
}
