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

    // RS256 access-token signing. PEMs come from env / Secrets Manager and are NEVER
    // committed. Generate a dev keypair locally (see docs/RUNBOOK.md) and export
    // IDENTITY_JWT_PRIVATE_KEY / IDENTITY_JWT_PUBLIC_KEY. Rotation (multiple kids) lands
    // with the signing_keys table.
    'jwt' => [
        'issuer' => env('IDENTITY_JWT_ISSUER', 'unero.identity-service'),
        'audience' => env('IDENTITY_JWT_AUDIENCE', 'unero-internal'),
        'access_ttl' => (int) env('IDENTITY_JWT_ACCESS_TTL', 900), // 15 minutes
        'refresh_ttl' => (int) env('IDENTITY_JWT_REFRESH_TTL', 2592000), // 30 days
        'kid' => (string) env('IDENTITY_JWT_KID', 'dev'),
        'private_key' => (string) env('IDENTITY_JWT_PRIVATE_KEY', ''),
        'public_key' => (string) env('IDENTITY_JWT_PUBLIC_KEY', ''),
        // JSON object {kid: public_pem} of previous keys still trusted for verification during a
        // rotation. Kept as a raw string so config:cache stays serialisable; parsed in the provider.
        'verify_only_public_keys' => (string) env('IDENTITY_JWT_VERIFY_ONLY_PUBLIC_KEYS', ''),
    ],

    'api_key' => [
        // Environment label baked into keys: unero_<env>_<prefix>.<secret>.
        'env' => (string) env('IDENTITY_API_KEY_ENV', 'live'),
        // last_used_at is only rewritten when it is older than this many seconds (hot-key throttle).
        'touch_throttle' => (int) env('IDENTITY_API_KEY_TOUCH_THROTTLE', 3600),
        // On rotation the old key stays usable for this many seconds before it passively expires.
        'rotation_grace' => (int) env('IDENTITY_API_KEY_ROTATION_GRACE', 86400), // 24 hours
    ],

    'email_verification' => [
        // Lifetime of a single-use email verification token, in seconds.
        'ttl' => (int) env('IDENTITY_EMAIL_VERIFICATION_TTL', 86400), // 24 hours
    ],

    'password_reset' => [
        // Validity window (seconds) for a reset: the delivery_ref must be materialised and the token
        // redeemed within it.
        'ttl' => (int) env('IDENTITY_PASSWORD_RESET_TTL', 3600), // 1 hour
    ],

    'mfa' => [
        'issuer' => (string) env('IDENTITY_MFA_ISSUER', 'Unero'),
        'period' => (int) env('IDENTITY_MFA_PERIOD', 30),
        'digits' => (int) env('IDENTITY_MFA_DIGITS', 6),
        'window' => (int) env('IDENTITY_MFA_WINDOW', 1), // +/- steps of clock-skew tolerance
        // Lifetime (seconds) of the post-password MFA challenge before the user must log in again.
        'challenge_ttl' => (int) env('IDENTITY_MFA_CHALLENGE_TTL', 300), // 5 minutes
    ],

    // Sibling platform repositories (during foundation phase; see UNERO_LINKS.md).
    'platform' => [
        'terraform' => env('UNERO_PLATFORM_TERRAFORM', 'https://github.com/nellyurh/unero-platform-terraform'),
        'schemas' => env('UNERO_SHARED_SCHEMAS', 'https://github.com/nellyurh/unero-shared-schemas'),
        'github' => env('UNERO_PLATFORM_GITHUB', 'https://github.com/nellyurh/platform-github'),
    ],
];
