<?php

namespace App\Support;

class FaceVerification
{
    public const MATCH_THRESHOLD = 0.42;
    public const LIVENESS_CHALLENGES = [
        'smile',
        'turn-left',
        'turn-right',
    ];

    public static function matchThreshold(): float
    {
        return self::MATCH_THRESHOLD;
    }

    public static function livenessChallenges(): array
    {
        return self::LIVENESS_CHALLENGES;
    }

    public static function isLivenessChallenge(?string $challenge): bool
    {
        return in_array($challenge, self::LIVENESS_CHALLENGES, true);
    }

    public static function livenessLabel(?string $challenge): ?string
    {
        return match ($challenge) {
            'smile' => 'Smile',
            'turn-left' => 'Turn head left',
            'turn-right' => 'Turn head right',
            default => null,
        };
    }
}
