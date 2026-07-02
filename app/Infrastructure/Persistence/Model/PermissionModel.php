<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Model;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $name
 * @property string $resource
 * @property string $action
 * @property string|null $description
 * @property bool $is_system
 * @property CarbonImmutable $created_at
 * @property CarbonImmutable $updated_at
 */
final class PermissionModel extends Model
{
    protected $table = 'permissions';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    protected $fillable = [
        'id', 'name', 'resource', 'action', 'description', 'is_system',
        'created_at', 'updated_at',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'created_at' => 'immutable_datetime',
        'updated_at' => 'immutable_datetime',
    ];
}
