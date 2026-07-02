<?php

declare(strict_types=1);

namespace App\Application\User;

use App\Application\User\Result\UserProfile;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\ValueObject\Email;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Domain\Identity\User\ValueObject\Username;

/** Read-side queries returning a UserProfile projection. No mutation, no audit. */
final readonly class GetUser
{
    public function __construct(private UserRepository $users) {}

    public function byId(string $userId): ?UserProfile
    {
        return $this->project($this->users->findById(new UserId($userId)));
    }

    public function byEmail(string $email): ?UserProfile
    {
        return $this->project($this->users->findByEmail(new Email($email)));
    }

    public function byUsername(string $username): ?UserProfile
    {
        return $this->project($this->users->findByUsername(new Username($username)));
    }

    private function project(?User $user): ?UserProfile
    {
        return $user instanceof User ? UserProfile::fromUser($user) : null;
    }
}
