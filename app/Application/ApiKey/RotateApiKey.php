<?php

declare(strict_types=1);

namespace App\Application\ApiKey;

use App\Application\ApiKey\Command\RotateApiKeyCommand;
use App\Application\ApiKey\Result\ApiKeyRotatedResult;
use App\Application\ApiKey\Result\ApiKeyView;
use App\Application\Port\ApiKeyGenerator;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\ApiKey\ApiKey;
use App\Domain\Identity\ApiKey\Repository\ApiKeyRepository;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyId;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyPrefix;
use App\Domain\Identity\ApiKey\ValueObject\HashedApiKeySecret;
use DateInterval;

/**
 * Rotate an API key with zero downtime: issue a fresh key inheriting the old key's owner, name and
 * scopes, and put the old key into a grace window (its expiry is capped at now + grace) during which
 * it keeps working. The old key emits ApiKeyRotated; the new key emits ApiKeyCreated. The full new
 * key is returned once. A revoked key cannot be rotated (APIKEY_004).
 */
final readonly class RotateApiKey
{
    public function __construct(
        private ApiKeyRepository $keys,
        private ApiKeyGenerator $generator,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
        private string $envLabel,
        private int $graceSeconds,
    ) {}

    public function handle(RotateApiKeyCommand $c): ApiKeyRotatedResult
    {
        $old = $this->keys->getById(new ApiKeyId($c->apiKeyId));

        return $this->tx->transactional(function () use ($c, $old): ApiKeyRotatedResult {
            $now = $this->clock->now();
            $graceUntil = $now->add(new DateInterval('PT'.$this->graceSeconds.'S'));

            $material = $this->generator->generate();
            $fullKey = sprintf('unero_%s_%s.%s', $this->envLabel, $material->prefix, $material->secret);
            $replacementId = $this->keys->nextIdentity();

            $new = ApiKey::create(
                $replacementId,
                new ApiKeyPrefix($material->prefix),
                HashedApiKeySecret::fromHash(hash('sha256', $material->secret)),
                $old->name(),
                $old->owner(),
                $old->scopes(),
                null,
                $c->actorId,
                $now,
            );

            $old->markRotated($replacementId, $graceUntil, $now);

            $this->keys->save($old);
            $this->keys->save($new);

            $this->audit->record(
                'api_key.rotated',
                $c->actorId,
                'api_key:'.$old->id->value,
                [],
                ['replacement_id' => $replacementId->value, 'grace_until' => $graceUntil->format(DATE_RFC3339)],
                $c->requestId,
                null,
            );

            return new ApiKeyRotatedResult(
                ApiKeyView::fromKey($new),
                $fullKey,
                $old->id->value,
                $old->expiresAt()?->format(DATE_RFC3339),
            );
        });
    }
}
