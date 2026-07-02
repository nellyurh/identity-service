<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Illuminate\Database\Eloquent\Model;

final class OutboxEntryModel extends Model
{
    protected $table = 'outbox_entries';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id', 'event_type', 'event_version', 'schema_version', 'aggregate_type',
        'aggregate_id', 'payload_json', 'correlation_id', 'causation_id',
        'created_at', 'published_at',
    ];

    protected $casts = [
        'payload_json' => 'array',
        'event_version' => 'integer',
        'created_at' => 'immutable_datetime',
        'published_at' => 'immutable_datetime',
    ];
}
