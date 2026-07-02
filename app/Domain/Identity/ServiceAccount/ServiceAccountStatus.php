<?php

declare(strict_types=1);

namespace App\Domain\Identity\ServiceAccount;

enum ServiceAccountStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}
