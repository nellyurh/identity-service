<?php

declare(strict_types=1);

namespace App\Domain\Identity\User\Repository;

use App\Domain\Identity\User\Exception\UserNotFound;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\ValueObject\Email;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Domain\Identity\User\ValueObject\Username;

interface UserRepository
{
    public function findById(UserId $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function findByUsername(Username $username): ?User;

    /** @throws UserNotFound */
    public function getById(UserId $id): User;

    public function existsByEmail(Email $email): bool;

    public function existsByUsername(Username $username): bool;

    /** Persist the aggregate and drain its recorded events to the outbox atomically. */
    public function save(User $user): void;

    public function nextIdentity(): UserId;
}
