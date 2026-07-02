<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\ServiceAccount;

use App\Domain\Identity\ServiceAccount\Event\ServiceAccountCreated;
use App\Domain\Identity\ServiceAccount\Exception\ServiceAccountNotActive;
use App\Domain\Identity\ServiceAccount\ServiceAccount;
use App\Domain\Identity\ServiceAccount\ValueObject\HashedSecret;
use App\Domain\Identity\ServiceAccount\ValueObject\Scope;
use App\Domain\Identity\ServiceAccount\ValueObject\ScopeCollection;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceAccountId;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceName;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ServiceAccountTest extends TestCase
{
    private function newAccount(): ServiceAccount
    {
        return ServiceAccount::create(
            new ServiceAccountId((string) new Ulid),
            new ServiceName('wallet'),
            HashedSecret::fromHash('$argon2id$secret'),
            ScopeCollection::fromStrings(['wallet.credit', 'wallet.debit']),
            new DateTimeImmutable('2026-07-02T10:00:00+00:00'),
        );
    }

    public function test_create_records_event_without_secret(): void
    {
        $account = $this->newAccount();
        $events = $account->releaseEvents();

        $this->assertInstanceOf(ServiceAccountCreated::class, $events[0]);
        $payload = $events[0]->payload();
        $this->assertSame('wallet', $payload['name']);
        $this->assertContains('wallet.credit', $payload['scopes']);
        $this->assertArrayNotHasKey('secret', $payload);
        $this->assertArrayNotHasKey('secret_hash', $payload);
    }

    public function test_scope_membership(): void
    {
        $account = $this->newAccount();
        $this->assertTrue($account->hasScope(new Scope('wallet.credit')));
        $this->assertFalse($account->hasScope(new Scope('billing.refund')));
    }

    public function test_disabled_account_cannot_authenticate(): void
    {
        $account = $this->newAccount();
        $account->disable(new DateTimeImmutable);

        $this->expectException(ServiceAccountNotActive::class);
        $account->assertCanAuthenticate();
    }
}
