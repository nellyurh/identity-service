<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Domain\Identity\ServiceAccount\Exception\ServiceAccountNotFound;
use App\Domain\Identity\ServiceAccount\ServiceAccount;
use App\Domain\Identity\ServiceAccount\ValueObject\HashedSecret;
use App\Domain\Identity\ServiceAccount\ValueObject\ScopeCollection;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceAccountId;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceName;
use App\Infrastructure\Outbox\OutboxWriter;
use App\Infrastructure\Persistence\Repository\EloquentServiceAccountRepository;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentServiceAccountRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentServiceAccountRepository $accounts;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->accounts = new EloquentServiceAccountRepository(new OutboxWriter);
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function newAccount(string $name = 'wallet'): ServiceAccount
    {
        return ServiceAccount::create(
            $this->accounts->nextIdentity(),
            new ServiceName($name),
            HashedSecret::fromHash('sha256$old'),
            ScopeCollection::fromStrings(['wallet.credit', 'wallet.debit']),
            $this->now,
        );
    }

    public function test_save_persists_scopes_and_emits_created(): void
    {
        $account = $this->newAccount();
        $this->accounts->save($account);

        $this->assertDatabaseHas('service_accounts', ['id' => $account->id->value, 'name' => 'wallet', 'status' => 'active']);
        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'ServiceAccountCreated', 'aggregate_type' => 'ServiceAccount']);

        $loaded = $this->accounts->getById(new ServiceAccountId($account->id->value));
        $this->assertSame(['wallet.credit', 'wallet.debit'], $loaded->scopes()->toArray());
        $this->assertTrue($loaded->secretHash()->equals(HashedSecret::fromHash('sha256$old')));
    }

    public function test_rotate_updates_hash_and_emits_event(): void
    {
        $account = $this->newAccount();
        $this->accounts->save($account);

        $reloaded = $this->accounts->getById(new ServiceAccountId($account->id->value));
        $reloaded->rotateSecret(HashedSecret::fromHash('sha256$new'), $this->now);
        $this->accounts->save($reloaded);

        $again = $this->accounts->getById(new ServiceAccountId($account->id->value));
        $this->assertTrue($again->secretHash()->equals(HashedSecret::fromHash('sha256$new')));
        $this->assertDatabaseHas('outbox_entries', ['event_type' => 'ServiceAccountCredentialRotated']);
    }

    public function test_find_by_name_and_all_ordered(): void
    {
        $this->accounts->save($this->newAccount('wallet'));
        $this->accounts->save($this->newAccount('billing'));

        $this->assertNotNull($this->accounts->findByName(new ServiceName('wallet')));
        $this->assertTrue($this->accounts->existsByName(new ServiceName('billing')));

        $names = array_map(static fn (ServiceAccount $a): string => $a->name()->value, $this->accounts->all());
        $this->assertSame(['billing', 'wallet'], $names);
    }

    public function test_get_by_id_throws_when_missing(): void
    {
        $this->expectException(ServiceAccountNotFound::class);
        $this->accounts->getById($this->accounts->nextIdentity());
    }
}
