<?php

declare(strict_types=1);

namespace App\Domain\Identity\Mfa\Exception;

use App\Domain\Shared\Exception\DomainException;

final class MfaNotEnrolled extends DomainException
{
    public static function withUser(string $userId): self
    {
        $e = new self('No pending MFA enrollment to confirm.');
        $e->detail = ['user_id' => $userId];

        return $e;
    }

    public function errorCode(): string
    {
        return 'MFA_003';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
