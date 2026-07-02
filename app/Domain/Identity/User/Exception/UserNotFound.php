<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Exception;

use App\Domain\Shared\Exception\DomainException;

final class UserNotFound extends DomainException
{
    public static function withId(string $id): self
    {
        $e = new self("No user with id {$id}.");
        $e->detail = ['user_id' => $id];

        return $e;
    }

    public static function withEmail(string $email): self
    {
        $e = new self('No user with the given email.');
        $e->detail = ['email' => $email];

        return $e;
    }

    public function errorCode(): string
    {
        return 'USER_001';
    }

    public function httpStatus(): int
    {
        return 404;
    }
}
