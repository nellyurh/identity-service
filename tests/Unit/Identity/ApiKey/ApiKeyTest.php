<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\ApiKey;

use App\Domain\Identity\ApiKey\ApiKey;
use App\Domain\Identity\ApiKey\Event\ApiKeyCreated;
use App\Domain\Identity\ApiKey\Event\ApiKeyRevoked;
use App\Domain\Identity\ApiKey\OwnerType;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyId;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyOwner;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyPrefix;
use App\Domain\Identity\ApiKey\ValueObject\HashedApiKeySecret;
use App\Domain\Identity\ServiceAccount\ValueObject\Scope;
use App\Domain\Identity\ServiceAccount\ValueObject\ScopeCollection;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ApiKeyTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function newKey(string $secret = 'the-secret', ?DateTimeImmutable $expiresAt = null): ApiKey
    {
        return ApiKey::create(
            new ApiKeyId((string) new Ulid),
            new ApiKeyPrefix('ab12cd34ef56'),
            HashedApiKeySecret::fromHash(hash('sha256', $secret)),
            'CI deploy key',
            new ApiKeyOwner(OwnerType::ServiceAccount, (string) new Ulid),
            ScopeCollection::fromStrings(['wallet.read']),
            $expiresAt,
            'admin-1',
            $this->now,
        );
    }

    public function test_create_records_event_without_secret(): void
    {
        $key = $this->newKey();
        $events = $key->releaseEvents();

        $this->assertCount(1, $events);
        $this->assertInstanceOf(ApiKeyCreated::class, $events[0]);
        $payload = $events[0]->payload();
        $this->assertSame('ab12cd34ef56', $payload['prefix']);
        $this->assertSame('service_account', $payload['owner_type']);
        $this->assertContains('wallet.read', $payload['scopes']);
        $this->assertArrayNotHasKey('secret', $payload);
        $this->assertArrayNotHasKey('secret_hash', $payload);
    }

    public function test_verify_secret_is_constant_time_match(): void
    {
        $key = $this->newKey('super-secret-value');

        $this->assertTrue($key->verifySecret('super-secret-value'));
        $this->assertFalse($key->verifySecret('wrong-value'));
    }

    public function test_revoke_is_idempotent_and_records_once(): void
    {
        $key = $this->newKey();
        $key->releaseEvents();

        $key->revoke($this->now);
        $this->assertTrue($key->isRevoked());
        $this->assertFalse($key->isUsable($this->now));
        $events = $key->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ApiKeyRevoked::class, $events[0]);

        $key->revoke($this->now);
        $this->assertSame([], $key->releaseEvents());
    }

    public function test_expiry_affects_usability(): void
    {
        $expired = $this->newKey('s', $this->now->modify('-1 second'));
        $this->assertTrue($expired->isExpired($this->now));
        $this->assertFalse($expired->isUsable($this->now));

        $live = $this->newKey('s', $this->now->modify('+1 hour'));
        $this->assertFalse($live->isExpired($this->now));
        $this->assertTrue($live->isUsable($this->now));
    }

    public function test_touch_is_throttled(): void
    {
        $key = $this->newKey();

        $this->assertTrue($key->touch($this->now, 3600));               // first use: advances
        $this->assertFalse($key->touch($this->now->modify('+10 seconds'), 3600)); // within window: no-op
        $this->assertTrue($key->touch($this->now->modify('+2 hours'), 3600));     // past window: advances
    }

    public function test_has_scope(): void
    {
        $key = $this->newKey();
        $this->assertTrue($key->hasScope(new Scope('wallet.read')));
        $this->assertFalse($key->hasScope(new Scope('wallet.write')));
    }
}
