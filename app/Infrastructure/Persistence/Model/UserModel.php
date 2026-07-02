<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $email
 * @property string $username
 * @property string $password_hash
 * @property string $status
 * @property CarbonImmutable|null $email_verified_at
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class UserModel extends Model
{
    protected $table = 'users';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id', 'email', 'username', 'password_hash', 'status',
        'email_verified_at', 'created_at', 'updated_at',
    ];

    protected $casts = [
        'email_verified_at' => 'immutable_datetime',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
