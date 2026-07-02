<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Identity\User\Exception\UserNotFound;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\ValueObject\Email;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Domain\Identity\User\ValueObject\Username;
use App\Domain\Shared\Event\DomainEvent;
use Symfony\Component\Uid\Ulid;

/**
 * In-memory UserRepository for application-service tests. save() drains recorded events the
 * way the Eloquent adapter will (to the outbox), so tests can assert what would be published.
 */
final class InMemoryUserRepository implements UserRepository
{
    /** @var array<string,User> */
    private array $byId = [];

    /** @var list<DomainEvent> */
    public array $publishedEvents = [];

    public function findById(UserId $id): ?User
    {
        return $this->byId[$id->value] ?? null;
    }

    public function findByEmail(Email $email): ?User
    {
        foreach ($this->byId as $user) {
            if ($user->email()->equals($email)) {
                return $user;
            }
        }

        return null;
    }

    public function findByUsername(Username $username): ?User
    {
        foreach ($this->byId as $user) {
            if ($user->username()->equals($username)) {
                return $user;
            }
        }

        return null;
    }

    public function getById(UserId $id): User
    {
        return $this->findById($id) ?? throw UserNotFound::withId($id->value);
    }

    public function existsByEmail(Email $email): bool
    {
        return $this->findByEmail($email) instanceof User;
    }

    public function existsByUsername(Username $username): bool
    {
        return $this->findByUsername($username) instanceof User;
    }

    public function save(User $user): void
    {
        $this->byId[$user->id->value] = $user;
        foreach ($user->releaseEvents() as $event) {
            $this->publishedEvents[] = $event;
        }
    }

    public function nextIdentity(): UserId
    {
        return new UserId((string) new Ulid);
    }
}
