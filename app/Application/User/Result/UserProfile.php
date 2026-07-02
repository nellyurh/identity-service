<?php

declare(strict_types=1);

namespace App\Application\User\Result;

use App\Domain\Identity\Role\ValueObject\RoleId;
use App\Domain\Identity\User\User;

final readonly class UserProfile
{
    /** @param list<string> $roles role ids */
    public function __construct(
        public string $userId,
        public string $email,
        public string $username,
        public string $status,
        public bool $emailVerified,
        public array $roles,
        public int $authzVersion,
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
            roles: array_map(static fn (RoleId $r): string => $r->value, $user->roleIds()),
            authzVersion: $user->authzVersion(),
            createdAt: $user->createdAt()->format(DATE_RFC3339),
            updatedAt: $user->updatedAt()->format(DATE_RFC3339),
        );
    }
}
