<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $secret
 * @property string $status
 * @property CarbonImmutable|null $confirmed_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class TotpCredentialModel extends Model
{
    protected $table = 'totp_credentials';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id', 'user_id', 'secret', 'status', 'confirmed_at', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'confirmed_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
