<?php

namespace App\Support;

class StatusBadge
{
    public const FALLBACK = 'bg-slate-50 text-slate-700 ring-slate-200 dark:bg-slate-900 dark:text-slate-200 dark:ring-slate-600';

    private const SUCCESS = 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-700/60';
    private const INFO = 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/40 dark:text-blue-200 dark:ring-blue-700/60';
    private const WARNING = 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-700/60';
    private const DANGER = 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-700/60';
    private const VIOLET = 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-950/40 dark:text-violet-200 dark:ring-violet-700/60';
    private const ORANGE = 'bg-orange-50 text-orange-700 ring-orange-200 dark:bg-orange-950/40 dark:text-orange-200 dark:ring-orange-700/60';
    private const SKY = 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950/40 dark:text-sky-200 dark:ring-sky-700/60';

    public static function patrol(?string $status): string
    {
        return match (self::key($status)) {
            'valid', 'verified', 'resolved' => self::SUCCESS,
            'suspicious' => self::WARNING,
            'invalid', 'failed' => self::DANGER,
            'pending_face', 'pending_selfie', 'pending_checklist', 'submitted' => self::INFO,
            'profile_incomplete' => self::VIOLET,
            'outside_schedule' => self::ORANGE,
            default => self::FALLBACK,
        };
    }

    public static function incidentStatus(?string $status): string
    {
        return match (self::key($status)) {
            'submitted' => self::INFO,
            'under_review' => self::WARNING,
            'resolved' => self::SUCCESS,
            default => self::FALLBACK,
        };
    }

    public static function priority(?string $priority): string
    {
        return match (self::key($priority)) {
            'low' => self::SKY,
            'high' => self::WARNING,
            'critical' => self::DANGER,
            default => self::FALLBACK,
        };
    }

    public static function severity(?string $severity): string
    {
        return match (self::key($severity)) {
            'ok' => self::SUCCESS,
            'warning' => self::WARNING,
            'danger' => self::DANGER,
            default => self::FALLBACK,
        };
    }

    public static function checklist(?string $status): string
    {
        return match (self::key($status)) {
            PatrolChecklist::STATUS_ISSUE => self::WARNING,
            PatrolChecklist::STATUS_NOT_APPLICABLE => self::FALLBACK,
            default => self::SUCCESS,
        };
    }

    private static function key(?string $value): string
    {
        return strtolower((string) $value);
    }
}
