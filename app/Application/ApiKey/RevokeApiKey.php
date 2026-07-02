<?php

declare(strict_types=1);

namespace App\Application\ApiKey;

use App\Application\ApiKey\Command\RevokeApiKeyCommand;
use App\Application\ApiKey\Result\ApiKeyView;
use App\Application\Port\AuditWriter;
use App\Application\Port\Clock;
use App\Application\Port\TransactionManager;
use App\Domain\Identity\ApiKey\Repository\ApiKeyRepository;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyId;

/** Revoke an API key (idempotent). Revocation is immediate; the key can never authenticate again. */
final readonly class RevokeApiKey
{
    public function __construct(
        private ApiKeyRepository $keys,
        private AuditWriter $audit,
        private Clock $clock,
        private TransactionManager $tx,
    ) {}

    public function handle(RevokeApiKeyCommand $c): ApiKeyView
    {
        $key = $this->keys->getById(new ApiKeyId($c->apiKeyId));

        return $this->tx->transactional(function () use ($c, $key): ApiKeyView {
            $key->revoke($this->clock->now());
            $this->keys->save($key);

            $this->audit->record(
                'api_key.revoked',
                $c->actorId,
                'api_key:'.$key->id->value,
                [],
                [],
                $c->requestId,
                null,
            );

            return ApiKeyView::fromKey($key);
        });
    }
}
