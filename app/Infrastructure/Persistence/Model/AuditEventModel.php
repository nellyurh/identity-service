<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Illuminate\Database\Eloquent\Model;

final class AuditEventModel extends Model
{
    protected $table = 'audit_events';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id', 'action', 'actor_id', 'target', 'before_json', 'after_json',
        'request_id', 'reason', 'created_at',
    ];

    protected $casts = [
        'before_json' => 'array',
        'after_json' => 'array',
        'created_at' => 'immutable_datetime',
    ];
}
