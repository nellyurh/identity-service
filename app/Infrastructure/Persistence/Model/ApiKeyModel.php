<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $prefix
 * @property string $secret_hash
 * @property string $name
 * @property string $owner_type
 * @property string $owner_id
 * @property list<string> $scopes
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $last_used_at
 * @property CarbonImmutable|null $revoked_at
 * @property string $created_by
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class ApiKeyModel extends Model
{
    protected $table = 'api_keys';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id', 'prefix', 'secret_hash', 'name', 'owner_type', 'owner_id', 'scopes',
        'expires_at', 'last_used_at', 'revoked_at', 'created_by', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'expires_at' => 'immutable_datetime',
        'last_used_at' => 'immutable_datetime',
        'revoked_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
