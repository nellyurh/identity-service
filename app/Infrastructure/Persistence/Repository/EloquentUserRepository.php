<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Repository;

use App\Domain\Identity\Role\ValueObject\RoleId;
use App\Domain\Identity\User\Exception\UserNotFound;
use App\Domain\Identity\User\Repository\UserRepository;
use App\Domain\Identity\User\User;
use App\Domain\Identity\User\UserStatus;
use App\Domain\Identity\User\ValueObject\Email;
use App\Domain\Identity\User\ValueObject\HashedPassword;
use App\Domain\Identity\User\ValueObject\UserId;
use App\Domain\Identity\User\ValueObject\Username;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Model\UserModel;
use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

/**
 * Eloquent adapter for UserRepository. save() upserts the aggregate and drains its recorded
 * domain events into the outbox in the SAME transaction (opened by the application service
 * via the TransactionManager port) — no dual write.
 */
final readonly class EloquentUserRepository implements UserRepository
{
    private const int EVENT_VERSION = 1;

    private const string SCHEMA_VERSION = '1.0.0';

    public function __construct(private OutboxWriter $outbox) {}

    public function findById(UserId $id): ?User
    {
        return $this->map(UserModel::query()->find($id->value));
    }

    public function findByEmail(Email $email): ?User
    {
        return $this->map(UserModel::query()->where('email', $email->value)->first());
    }

    public function findByUsername(Username $username): ?User
    {
        return $this->map(UserModel::query()->where('username', $username->value)->first());
    }

    public function getById(UserId $id): User
    {
        return $this->findById($id) ?? throw UserNotFound::withId($id->value);
    }

    public function existsByEmail(Email $email): bool
    {
        return UserModel::query()->where('email', $email->value)->exists();
    }

    public function existsByUsername(Username $username): bool
    {
        return UserModel::query()->where('username', $username->value)->exists();
    }

    public function save(User $user): void
    {
        UserModel::query()->updateOrCreate(
            ['id' => $user->id->value],
            [
                'email' => $user->email()->value,
                'username' => $user->username()->value,
                'password_hash' => $user->passwordHash()->value,
                'status' => $user->status()->value,
                'authz_version' => $user->authzVersion(),
                'email_verified_at' => $user->emailVerifiedAt(),
                'created_at' => $user->createdAt(),
                'updated_at' => $user->updatedAt(),
            ],
        );

        $this->syncRoles($user);

        foreach ($user->releaseEvents() as $event) {
            $this->outbox->write(
                $event->eventType(),
                self::EVENT_VERSION,
                self::SCHEMA_VERSION,
                'User',
                $user->id->value,
                $event->payload(),
            );
        }
    }

    public function nextIdentity(): UserId
    {
        return new UserId((string) new Ulid);
    }

    private function map(?UserModel $model): ?User
    {
        if (! $model instanceof UserModel) {
            return null;
        }

        return User::reconstitute(
            new UserId($model->id),
            new Email($model->email),
            new Username($model->username),
            HashedPassword::fromHash($model->password_hash),
            UserStatus::from($model->status),
            $this->toImmutable($model->email_verified_at),
            $this->toImmutable($model->created_at) ?? new DateTimeImmutable,
            $this->toImmutable($model->updated_at) ?? new DateTimeImmutable,
            $this->roleIds($model->id),
            $model->authz_version,
        );
    }

    /** Re-sync the user_roles pivot from the aggregate's role set (tenant-global for now). */
    private function syncRoles(User $user): void
    {
        DB::table('user_roles')->where('user_id', $user->id->value)->delete();

        $now = Date::now()->toImmutable();
        $rows = [];
        foreach ($user->roleIds() as $roleId) {
            $rows[] = [
                'user_id' => $user->id->value,
                'role_id' => $roleId->value,
                'tenant_id' => '',
                'created_at' => $now,
            ];
        }
        if ($rows !== []) {
            DB::table('user_roles')->insert($rows);
        }
    }

    /** @return list<RoleId> */
    private function roleIds(string $userId): array
    {
        $ids = [];
        foreach (DB::table('user_roles')->where('user_id', $userId)->pluck('role_id') as $roleId) {
            $ids[] = new RoleId((string) $roleId);
        }

        return $ids;
    }

    private function toImmutable(?DateTimeInterface $value): ?DateTimeImmutable
    {
        if (! $value instanceof DateTimeInterface) {
            return null;
        }

        return DateTimeImmutable::createFromInterface($value);
    }
}
