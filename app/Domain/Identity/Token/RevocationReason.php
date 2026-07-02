<?php

declare(strict_types=1);

namespace App\Domain\Identity\Token;

/**
 * Why a refresh-token family was revoked. Stable machine values carried on TokenRevoked and
 * mirrored in the shared schema's enum, so consumers branch on intent rather than prose.
 */
enum RevocationReason: string
{
    case Logout = 'logout';
    case ReuseDetected = 'reuse_detected';
    case PasswordChange = 'password_change';
}
