<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $user_id
 * @property string $code_hash
 * @property CarbonImmutable|null $used_at
 * @property CarbonImmutable $created_at
 */
final class RecoveryCodeModel extends Model
{
    protected $table = 'recovery_codes';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = ['id', 'user_id', 'code_hash', 'used_at', 'created_at'];

    protected $casts = [
        'used_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
    ];
}
