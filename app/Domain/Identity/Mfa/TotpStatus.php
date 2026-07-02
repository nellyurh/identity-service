<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa;

enum TotpStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Disabled = 'disabled';
}
