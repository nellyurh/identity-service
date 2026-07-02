<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\User\Exception\UserNotFound;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\ValueObject\Email;
use App\Domain\Identity\User\ValueObject\HashedPassword;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Domain\Identity\User\ValueObject\Username;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Repository\EloquentUserRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\TestCase;

final class EloquentUserRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private function repo(): EloquentUserRepository
    {
        return new EloquentUserRepository(new OutboxWriter);
    }

    private function newUser(EloquentUserRepository $repo): User
    {
        return User::register(
            $repo->nextIdentity(),
            new Email('ada@unero.com'),
            new Username('ada_l'),
            HashedPassword::fromHash('argon2id$stored'),
            new DateTimeImmutable('2026-07-02T10:00:00+00:00'),
        );
    }

    public function test_save_persists_user_and_writes_outbox_event(): void
    {
        $repo = $this->repo();
        $user = $this->newUser($repo);

        $repo->save($user);

        $this->assertDatabaseHas('users', [
            'id' => $user->id->value,
            'email' => 'ada@unero.com',
            'username' => 'ada_l',
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('outbox_entries', [
            'event_type' => 'UserRegistered',
            'aggregate_type' => 'User',
            'aggregate_id' => $user->id->value,
        ]);
    }

    public function test_find_by_email_reconstitutes_the_aggregate(): void
    {
        $repo = $this->repo();
        $user = $this->newUser($repo);
        $repo->save($user);

        $found = $repo->findByEmail(new Email('ada@unero.com'));

        $this->assertNotNull($found);
        $this->assertTrue($found->id->equals($user->id));
        $this->assertSame('active', $found->status()->value);
        $this->assertFalse($found->isEmailVerified());
        $this->assertTrue($found->passwordHash()->equals(HashedPassword::fromHash('argon2id$stored')));
    }

    public function test_get_by_id_throws_when_missing(): void
    {
        $this->expectException(UserNotFound::class);
        $this->repo()->getById(new UserId((string) new Ulid));
    }

    public function test_existence_checks(): void
    {
        $repo = $this->repo();
        $repo->save($this->newUser($repo));

        $this->assertTrue($repo->existsByEmail(new Email('ada@unero.com')));
        $this->assertFalse($repo->existsByEmail(new Email('nobody@unero.com')));
        $this->assertTrue($repo->existsByUsername(new Username('ada_l')));
    }
}
