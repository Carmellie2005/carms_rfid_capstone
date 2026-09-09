<?php

namespace App\Support;

class FaceVerification
{
    public const MATCH_THRESHOLD = 0.42;
    public const REGISTRATION_SAMPLE_TYPES = [
        'front_neutral' => 'Front neutral',
        'front_smile' => 'Front smile',
        'slight_left' => 'Slight left turn',
        'slight_right' => 'Slight right turn',
        'low_light' => 'Normal or low-light',
    ];
    public const LIVENESS_CHALLENGES = [
        'smile',
        'turn-left',
        'turn-right',
    ];

    public static function enabled(): bool
    {
        return (bool) config('features.face_verification', false);
    }

    public static function matchThreshold(): float
    {
        return self::MATCH_THRESHOLD;
    }

    public static function registrationSampleTypes(): array
    {
        return self::REGISTRATION_SAMPLE_TYPES;
    }

    public static function registrationSampleType(string $type): ?string
    {
        return self::REGISTRATION_SAMPLE_TYPES[$type] ?? null;
    }

    public static function requiredRegistrationSampleCount(): int
    {
        return count(self::REGISTRATION_SAMPLE_TYPES);
    }

    public static function hasCompleteRegistration(iterable $samples): bool
    {
        $validTypes = [];

        foreach ($samples as $sample) {
            $descriptor = $sample->descriptor ?? null;
            $type = $sample->capture_type ?? null;

            if (isset(self::REGISTRATION_SAMPLE_TYPES[$type]) && self::isValidDescriptor($descriptor)) {
                $validTypes[$type] = true;
            }
        }

        return count($validTypes) === self::requiredRegistrationSampleCount();
    }

    public static function isValidDescriptor(mixed $descriptor): bool
    {
        return is_array($descriptor)
            && count($descriptor) === 128
            && collect($descriptor)->every(fn ($item) => is_numeric($item));
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
