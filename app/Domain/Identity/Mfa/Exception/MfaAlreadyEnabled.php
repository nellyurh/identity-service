<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa\Exception;

use App\Domain\Shared\Exception\DomainException;

final class MfaAlreadyEnabled extends DomainException
{
    public static function withUser(string $userId): self
    {
        $e = new self('MFA is already enabled for this user.');
        $e->detail = ['user_id' => $userId];

        return $e;
    }

    public function errorCode(): string
    {
        return 'MFA_001';
    }

    public function httpStatus(): int
    {
        return 409;
    }
}
