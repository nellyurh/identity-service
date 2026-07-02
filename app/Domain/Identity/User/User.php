<?php

declare(strict_types=1);

namespace App\Domain\Identity\User;

use App\Domain\Identity\User\Event\EmailVerified;
use App\Domain\Identity\User\Event\PasswordChanged;
use App\Domain\Identity\User\Event\UserActivated;
use App\Domain\Identity\User\Event\UserDisabled;
use App\Domain\Identity\User\Event\UserRegistered;
use App\Domain\Identity\User\Exception\AccountNotActive;
use App\Domain\Identity\User\Exception\EmailAlreadyVerified;
use App\Domain\Identity\User\ValueObject\Email;
use App\Domain\Identity\User\ValueObject\HashedPassword;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Domain\Identity\User\ValueObject\Username;
use App\Domain\Shared\Event\DomainEvent;
use DateTimeImmutable;

/**
 * Aggregate root for a platform user. All state changes go through methods here, which keep
 * the invariants (a user can only authenticate when active; the password is only ever held
 * as a hash; the id is immutable). The repository persists the resulting state and drains
 * recorded events into the outbox.
 */
final class User
{
    /** @var list<DomainEvent> */
    private array $recordedEvents = [];

    private function __construct(
        public readonly UserId $id,
        private readonly Email $email,
        private readonly Username $username,
        private HashedPassword $passwordHash,
        private UserStatus $status,
        private ?DateTimeImmutable $emailVerifiedAt,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    /**
     * Register a new user. Starts active but with an unverified email; authentication is
     * permitted (status active), while email-gated actions check isEmailVerified().
     */
    public static function register(
        UserId $id,
        Email $email,
        Username $username,
        HashedPassword $passwordHash,
        DateTimeImmutable $now,
    ): self {
        $user = new self($id, $email, $username, $passwordHash, UserStatus::Active, null, $now, $now);

        $user->recordedEvents[] = new UserRegistered(
            userId: $id->value,
            emailVerified: false,
            occurredAt: $now->format(DATE_RFC3339),
        );

        return $user;
    }

    public static function reconstitute(
        UserId $id,
        Email $email,
        Username $username,
        HashedPassword $passwordHash,
        UserStatus $status,
        ?DateTimeImmutable $emailVerifiedAt,
        DateTimeImmutable $createdAt,
        DateTimeImmutable $updatedAt,
    ): self {
        return new self($id, $email, $username, $passwordHash, $status, $emailVerifiedAt, $createdAt, $updatedAt);
    }

    public function verifyEmail(DateTimeImmutable $now): void
    {
        if ($this->emailVerifiedAt instanceof DateTimeImmutable) {
            throw EmailAlreadyVerified::create();
        }
        $this->emailVerifiedAt = $now;
        $this->touch($now);

        $this->recordedEvents[] = new EmailVerified($this->id->value, $now->format(DATE_RFC3339));
    }

    /**
     * Replace the password hash. Verifying the current password (where required) is done by
     * the application service via the PasswordHasher port; the aggregate only ever accepts
     * an already-hashed value.
     */
    public function changePassword(HashedPassword $newHash, DateTimeImmutable $now): void
    {
        $this->passwordHash = $newHash;
        $this->touch($now);

        $this->recordedEvents[] = new PasswordChanged($this->id->value, $now->format(DATE_RFC3339));
    }

    public function disable(DateTimeImmutable $now): void
    {
        if ($this->status === UserStatus::Deleted) {
            throw AccountNotActive::because($this->status);
        }
        if ($this->status === UserStatus::Disabled) {
            return;
        }
        $this->status = UserStatus::Disabled;
        $this->touch($now);

        $this->recordedEvents[] = new UserDisabled($this->id->value, $now->format(DATE_RFC3339));
    }

    public function enable(DateTimeImmutable $now): void
    {
        if ($this->status === UserStatus::Deleted) {
            throw AccountNotActive::because($this->status);
        }
        if ($this->status === UserStatus::Active) {
            return;
        }
        $this->status = UserStatus::Active;
        $this->touch($now);

        $this->recordedEvents[] = new UserActivated($this->id->value, 're_enabled', $now->format(DATE_RFC3339));
    }

    /** Soft delete: the row is retained for audit; the user can never authenticate again. */
    public function softDelete(DateTimeImmutable $now): void
    {
        $this->status = UserStatus::Deleted;
        $this->touch($now);
    }

    /** @throws AccountNotActive when the account is not in a state that permits login. */
    public function assertCanAuthenticate(): void
    {
        if (! $this->status->canAuthenticate()) {
            throw AccountNotActive::because($this->status);
        }
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerifiedAt instanceof DateTimeImmutable;
    }

    public function email(): Email
    {
        return $this->email;
    }

    public function username(): Username
    {
        return $this->username;
    }

    public function passwordHash(): HashedPassword
    {
        return $this->passwordHash;
    }

    public function status(): UserStatus
    {
        return $this->status;
    }

    public function emailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /** @return list<DomainEvent> Drains recorded events (called by the repository). */
    public function releaseEvents(): array
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        return $events;
    }

    private function touch(DateTimeImmutable $now): void
    {
        $this->updatedAt = $now;
    }
}
