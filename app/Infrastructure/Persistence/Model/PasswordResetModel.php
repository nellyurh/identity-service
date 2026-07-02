<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $delivery_ref
 * @property string|null $token_hash
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $materialized_at
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable $created_at
 */
final class PasswordResetModel extends Model
{
    protected $table = 'password_resets';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id', 'user_id', 'delivery_ref', 'token_hash',
        'expires_at', 'materialized_at', 'used_at', 'created_at',
    ];

    protected $casts = [
        'expires_at' => 'immutable_datetime',
        'materialized_at' => 'immutable_datetime',
        'used_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}
