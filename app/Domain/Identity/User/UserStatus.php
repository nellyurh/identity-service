<?php

declare(strict_types=1);

namespace App\Domain\Identity\User;

enum UserStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';
    case Deleted = 'deleted';

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
