<?php

declare(strict_types=1);

namespace App\Application\ApiKey;

use App\Application\ApiKey\Result\ApiKeyView;
use App\Domain\Identity\ApiKey\OwnerType;
use App\Domain\Identity\ApiKey\Repository\ApiKeyRepository;
use App\Domain\Identity\ApiKey\ValueObject\ApiKeyOwner;

/** List an owner's API keys (never exposes secrets). */
final readonly class ListApiKeys
{
    public function __construct(
        private ApiKeyRepository $keys,
    ) {}

    /** @return list<ApiKeyView> */
    public function handle(string $ownerType, string $ownerId): array
    {
        $owner = new ApiKeyOwner(OwnerType::from($ownerType), $ownerId);

        return array_map(
            ApiKeyView::fromKey(...),
            $this->keys->listByOwner($owner),
        );
    }
}
