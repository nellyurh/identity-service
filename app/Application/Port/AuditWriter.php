<?php

declare(strict_types=1);

namespace App\Application\Port;

/**
 * Writes an append-only audit row BEFORE the response returns (invariant:
 * audit-before-response). Every privileged/security-relevant action calls this.
 */
interface AuditWriter
{
    /**
     * @param  array<string,mixed>  $before
     * @param  array<string,mixed>  $after
     */
    public function record(
        string $action,
        string $actorId,
        string $target,
        array $before,
        array $after,
        string $requestId,
        ?string $reason,
    ): void;
}
