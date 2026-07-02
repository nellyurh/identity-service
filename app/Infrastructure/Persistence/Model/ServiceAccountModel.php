<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $secret_hash
 * @property string $status
 * @property list<string> $scopes
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class ServiceAccountModel extends Model
{
    protected $table = 'service_accounts';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id', 'name', 'secret_hash', 'status', 'scopes', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
