<?php

declare(strict_types=1);

namespace Tests\Unit\Identity\ApiKey;

use App\Domain\Identity\ApiKey\ApiKey;
use App\Domain\Identity\ApiKey\Event\ApiKeyRotated;
use App\Domain\Identity\ApiKey\Exception\ApiKeyNotActive;
use App\Domain\Identity\ApiKey\OwnerType;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyId;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyOwner;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyPrefix;
use App\Domain\Identity\ApiKey\ValueObject\HashedApiKeySecret;
use App\Domain\Identity\ServiceAccount\ValueObject\ScopeCollection;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Ulid;

final class ApiKeyRotationTest extends TestCase
{
    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = new DateTimeImmutable('2026-07-02T10:00:00+00:00');
    }

    private function newKey(?DateTimeImmutable $expiresAt = null): ApiKey
    {
        return ApiKey::create(
            new ApiKeyId((string) new Ulid),
            new ApiKeyPrefix('ab12cd34ef56'),
            HashedApiKeySecret::fromHash(hash('sha256', 's')),
            'CI key',
            new ApiKeyOwner(OwnerType::ServiceAccount, (string) new Ulid),
            ScopeCollection::fromStrings(['wallet.read']),
            $expiresAt,
            'admin-1',
            $this->now,
        );
    }

    public function test_mark_rotated_caps_expiry_and_records_event(): void
    {
        $key = $this->newKey();
        $key->releaseEvents();
        $graceUntil = $this->now->modify('+1 hour');

        $key->markRotated(new ApiKeyId((string) new Ulid), $graceUntil, $this->now);

        $this->assertEquals($graceUntil, $key->expiresAt());
        $this->assertTrue($key->isUsable($this->now));                       // still usable during grace
        $this->assertFalse($key->isUsable($this->now->modify('+2 hours')));  // expired after grace

        $events = $key->releaseEvents();
        $this->assertCount(1, $events);
        $this->assertInstanceOf(ApiKeyRotated::class, $events[0]);
    }

    public function test_mark_rotated_never_extends_a_sooner_expiry(): void
    {
        $sooner = $this->now->modify('+10 minutes');
        $key = $this->newKey($sooner);

        $key->markRotated(new ApiKeyId((string) new Ulid), $this->now->modify('+1 hour'), $this->now);

        $this->assertEquals($sooner, $key->expiresAt());
    }

    public function test_revoked_key_cannot_be_rotated(): void
    {
        $key = $this->newKey();
        $key->revoke($this->now);

        $this->expectException(ApiKeyNotActive::class);
        $key->markRotated(new ApiKeyId((string) new Ulid), $this->now->modify('+1 hour'), $this->now);
    }
}
