<?php

namespace App\Support;

use App\Models\Guard;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class DutyRoster
{
    /**
     * @param  Collection<int, Guard>  $guards
     * @return Collection<int, Guard>
     */
    public static function onDutyForDate(Carbon $date, Collection $guards): Collection
    {
        $assignedCodes = self::assignedCodes($date);
        $guardsByCode = $guards->keyBy(fn (Guard $guard) => self::normalizeEmployeeNo($guard->employee_no));
        $onlineCutoff = now($date->getTimezone())->subMinutes((int) config('duty_roster.online_timeout_minutes', 3));

        return collect($assignedCodes)
            ->map(function (string $employeeNo) use ($guardsByCode, $onlineCutoff): ?Guard {
                $guard = $guardsByCode->get(self::normalizeEmployeeNo($employeeNo));

                if (! $guard) {
                    return null;
                }

                $guard->setAttribute(
                    'duty_label',
                    in_array($employeeNo, self::rotationGuards(), true) ? '24-hour duty, 8:00 AM start' : 'Evening duty'
                );
                $guard->setAttribute(
                    'duty_state',
                    $guard->user?->last_seen_at?->greaterThanOrEqualTo($onlineCutoff) ? 'online' : 'offline'
                );

                return $guard;
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, Guard>  $guards
     * @return Collection<int, Guard>
     */
    public static function offDutyForDate(Carbon $date, Collection $guards): Collection
    {
        $assignedCodes = collect(self::assignedCodes($date))
            ->map(fn (string $employeeNo) => self::normalizeEmployeeNo($employeeNo));

        return $guards
            ->filter(fn (Guard $guard) => ! $assignedCodes->contains(self::normalizeEmployeeNo($guard->employee_no)))
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public static function assignedCodes(Carbon $date): array
    {
        $rotation = self::rotationGuards();

        if ($rotation === []) {
            return self::fixedEveningGuards();
        }

        $timezone = config('duty_roster.timezone', config('app.timezone'));
        $startDate = Carbon::parse(config('duty_roster.rotation_start_date'), $timezone)->startOfDay();
        $date = $date->copy()->timezone($timezone);
        $rotationStartHour = (int) config('duty_roster.rotation_start_hour', 8);

        if ($date->hour < $rotationStartHour) {
            $date->subDay();
        }

        $date->startOfDay();
        $daysFromStart = (int) $startDate->diffInDays($date, false);
        $startIndex = array_search(
            self::normalizeEmployeeNo((string) config('duty_roster.rotation_start_guard')),
            array_map([self::class, 'normalizeEmployeeNo'], $rotation),
            true
        );
        $startIndex = $startIndex === false ? 0 : $startIndex;
        $rotationIndex = (($startIndex + $daysFromStart) % count($rotation) + count($rotation)) % count($rotation);

        return array_values(array_unique([
            $rotation[$rotationIndex],
            ...self::fixedEveningGuards(),
        ]));
    }

    /**
     * @return array<int, string>
     */
    private static function rotationGuards(): array
    {
        return array_values(array_filter(config('duty_roster.rotation_guards', [])));
    }

    /**
     * @return array<int, string>
     */
    private static function fixedEveningGuards(): array
    {
        return array_values(array_filter(config('duty_roster.fixed_evening_guards', [])));
    }

    public static function normalizeEmployeeNo(string $employeeNo): string
    {
        $employeeNo = strtoupper(trim($employeeNo));

        return preg_replace_callback(
            '/^(.*?)-0*(\d+)$/',
            fn (array $matches) => $matches[1].'-'.((int) $matches[2]),
            $employeeNo
        ) ?: $employeeNo;
    }
}
