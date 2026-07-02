<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $family_id
 * @property string $token_hash
 * @property string $access_jti
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable|null $rotated_at
 * @property string|null $replaced_by
 * @property CarbonImmutable|null $revoked_at
 */
final class RefreshTokenModel extends Model
{
    protected $table = 'refresh_tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id', 'user_id', 'family_id', 'token_hash', 'access_jti',
        'expires_at', 'created_at', 'rotated_at', 'replaced_by', 'revoked_at',
    ];

    protected $casts = [
        'expires_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'rotated_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
    ];
}
