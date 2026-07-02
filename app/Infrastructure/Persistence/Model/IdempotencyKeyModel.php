<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Illuminate\Database\Eloquent\Model;

final class IdempotencyKeyModel extends Model
{
    protected $table = 'idempotency_keys';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id', 'idempotency_key', 'request_hash', 'response_code',
        'response_body', 'created_at',
    ];

    protected $casts = ['created_at' => 'immutable_datetime'];
}
