<?php

namespace App\Support;

class FaceVerification
{
    public const MATCH_THRESHOLD = 0.42;

    public static function matchThreshold(): float
    {
        return self::MATCH_THRESHOLD;
    }
}
