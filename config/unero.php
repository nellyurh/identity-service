<?php

declare(strict_types=1);

return [
    // AWS region for SDK clients (read here so it is captured when config is cached).
    'aws_region' => env('AWS_REGION', 'af-south-1'),

    // Event publishing target (EventBridge bus provisioned by unero-platform-terraform).
    'event_bus' => env('EVENT_BUS_NAME', 'unero-dev'),

    // Base URL for machine-readable error documentation (shared error envelope docs_url).
    'docs_url' => env('UNERO_DOCS_URL', 'https://docs.unero.com/errors'),

    // Argon2id password hashing parameters (Infrastructure\Security\ArgonPasswordHasher).
    // Tunable per environment; raise cost over time and needsRehash() upgrades on next login.
    'password' => [
        'memory_cost' => (int) env('ARGON_MEMORY_COST', 65536), // KiB (64 MB)
        'time_cost' => (int) env('ARGON_TIME_COST', 4),
        'threads' => (int) env('ARGON_THREADS', 1),
    ],

    // Sibling platform repositories (during foundation phase; see UNERO_LINKS.md).
    'platform' => [
        'terraform' => env('UNERO_PLATFORM_TERRAFORM', 'https://github.com/nellyurh/unero-platform-terraform'),
        'schemas' => env('UNERO_SHARED_SCHEMAS', 'https://github.com/nellyurh/unero-shared-schemas'),
        'github' => env('UNERO_PLATFORM_GITHUB', 'https://github.com/nellyurh/platform-github'),
    ],
];
