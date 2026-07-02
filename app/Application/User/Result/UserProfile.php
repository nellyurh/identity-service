<?php

declare(strict_types=1);

namespace App\Application\User\Result;

use App\Domain\Identity\User\User;

final readonly class UserProfile
{
    public function __construct(
        public string $userId,
        public string $email,
        public string $username,
        public string $status,
        public bool $emailVerified,
        public string $createdAt,
        public string $updatedAt,
    ) {}

    public static function fromUser(User $user): self
    {
        return new self(
            userId: $user->id->value,
            email: $user->email()->value,
            username: $user->username()->value,
            status: $user->status()->value,
            emailVerified: $user->isEmailVerified(),
            createdAt: $user->createdAt()->format(DATE_RFC3339),
            updatedAt: $user->updatedAt()->format(DATE_RFC3339),
        );
    }
}
