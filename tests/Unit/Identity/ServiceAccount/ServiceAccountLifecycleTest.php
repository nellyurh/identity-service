<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\ServiceAccount;

use App\Domain\Identity\ServiceAccount\Event\ServiceAccountCredentialRotated;
use App\Domain\Identity\ServiceAccount\Event\ServiceAccountDisabled;
use App\Domain\Identity\ServiceAccount\ServiceAccount;
use App\Domain\Identity\ServiceAccount\ValueObject\HashedSecret;
use App\Domain\Identity\ServiceAccount\ValueObject\ScopeCollection;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceAccountId;
use App\Domain\Identity\ServiceAccount\ValueObject\ServiceName;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ServiceAccountLifecycleTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function newAccount(): ServiceAccount
    {
        $account = ServiceAccount::create(
            new ServiceAccountId((string) new Ulid),
            new ServiceName('wallet'),
            HashedSecret::fromHash('sha256$old'),
            ScopeCollection::fromStrings(['wallet.credit']),
            $this->now,
        );
        $account->releaseEvents();

        return $account;
    }

    public function test_rotate_swaps_hash_and_records_event(): void
    {
        $account = $this->newAccount();

        $account->rotateSecret(HashedSecret::fromHash('sha256$new'), $this->now);

        $this->assertTrue($account->secretHash()->equals(HashedSecret::fromHash('sha256$new')));
        $events = $account->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ServiceAccountCredentialRotated::class, $events[0]);
    }

    public function test_disable_records_event_once_and_is_idempotent(): void
    {
        $account = $this->newAccount();

        $account->disable($this->now);
        $events = $account->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ServiceAccountDisabled::class, $events[0]);

        $account->disable($this->now);
        $this->assertSame([], $account->releaseEvents());
    }
}
