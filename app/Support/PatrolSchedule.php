<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class PatrolSchedule
{
    public const TIMEZONE = 'Asia/Manila';
    public const SCHEDULE_ENFORCED = false;
    public const TESTING_MODE = false;
    public const START_HOUR = 15;
    public const END_HOUR = 20;

    public static function isOpen(?Carbon $time = null): bool
    {
        if (! self::isScheduleEnforced()) {
            return true;
        }

        if (self::isTestingMode()) {
            return true;
        }

        $time = self::manilaTime($time);
        $hour = (int) $time->format('G');

        if (self::START_HOUR < self::END_HOUR) {
            return $hour >= self::START_HOUR && $hour < self::END_HOUR;
        }

        return $hour >= self::START_HOUR || $hour < self::END_HOUR;
    }

    public static function isScheduleEnforced(): bool
    {
        return self::SCHEDULE_ENFORCED;
    }

    public static function isTestingMode(): bool
    {
        return self::TESTING_MODE;
    }

    public static function windowLabel(): string
    {
        if (! self::isScheduleEnforced()) {
            return 'Open anytime';
        }

        return self::isTestingMode() ? 'Open anytime for testing' : self::scheduledWindowLabel();
    }

    public static function scheduledWindowLabel(): string
    {
        return '3:00 PM to 8:00 PM';
    }

    public static function testingNotice(): string
    {
        return 'Testing mode is active, so patrol scanning is open anytime for demo/testing.';
    }

    public static function closedMessage(): string
    {
        return 'Guard patrol scanning is only available from '.self::scheduledWindowLabel().'. Please scan again during the scheduled patrol window.';
    }

    public static function nextOpenAt(?Carbon $time = null): Carbon
    {
        $time = self::manilaTime($time);

        if (! self::isScheduleEnforced() || self::isOpen($time)) {
            return $time;
        }

        if ($time->hour >= self::END_HOUR && self::START_HOUR < self::END_HOUR) {
            return $time->copy()->addDay()->setTime(self::START_HOUR, 0);
        }

        return $time->copy()->setTime(self::START_HOUR, 0);
    }

    public static function manilaTime(?Carbon $time = null): Carbon
    {
        return ($time ?? now())->copy()->timezone(self::TIMEZONE);
    }
}
