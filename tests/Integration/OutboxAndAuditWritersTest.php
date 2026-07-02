<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Infrastructure\Audit\DatabaseAuditWriter;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Model\AuditEventModel;
use App\Infrastructure\Persistence\Model\OutboxEntryModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Tests\TestCase;

final class OutboxAndAuditWritersTest extends TestCase
{
    use RefreshDatabase;

    public function test_outbox_writer_persists_unpublished_row_with_correlation(): void
    {
        Context::add('correlation_id', 'corr-123');

        (new OutboxWriter)->write(
            eventType: 'UserCreated',
            eventVersion: 1,
            schemaVersion: '1.0.0',
            aggregateType: 'User',
            aggregateId: 'u-1',
            payload: ['user_id' => 'u-1'],
        );

        $row = OutboxEntryModel::query()->firstOrFail();
        $this->assertSame('UserCreated', $row->event_type);
        $this->assertSame('u-1', $row->aggregate_id);
        $this->assertSame(['user_id' => 'u-1'], $row->payload_json);
        $this->assertSame('corr-123', $row->correlation_id);
        $this->assertNull($row->published_at);
    }

    public function test_audit_writer_appends_row(): void
    {
        (new DatabaseAuditWriter)->record(
            action: 'user.created',
            actorId: 'admin-1',
            target: 'user:u-1',
            before: [],
            after: ['status' => 'active'],
            requestId: 'req-1',
            reason: null,
        );

        $row = AuditEventModel::query()->firstOrFail();
        $this->assertSame('user.created', $row->action);
        $this->assertSame('user:u-1', $row->target);
        $this->assertSame(['status' => 'active'], $row->after_json);
    }
}
