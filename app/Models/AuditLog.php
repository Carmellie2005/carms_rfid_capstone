<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'actor_name',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'properties',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function diagnosticSummary(): string
    {
        $diagnostic = $this->stringProperty('diagnostic');

        if ($diagnostic !== null) {
            return Str::limit($diagnostic, 160);
        }

        return Str::limit($this->description ?: 'Recorded system activity.', 160);
    }

    public function patrolWindowSummary(): string
    {
        $window = $this->stringProperty('patrol_window');

        if ($window !== null) {
            return Str::limit($window, 90);
        }

        if ($this->isPatrolRelated()) {
            return $this->resultKey() === 'outside_schedule' ? 'Outside patrol hours' : 'Within patrol hours';
        }

        return 'N/A';
    }

    public function resultLabel(): string
    {
        return match ($this->resultKey()) {
            'success' => 'Success',
            'completed' => 'Completed',
            'valid' => 'Valid',
            'verified' => 'Verified',
            'pending_face' => 'Pending Face',
            'pending_checklist' => 'Pending Checklist',
            'profile_incomplete' => 'Profile Incomplete',
            'outside_schedule' => 'Outside Schedule',
            'invalid' => 'Invalid',
            'failed' => 'Failed',
            'suspicious' => 'Needs Review',
            default => 'Recorded',
        };
    }

    public function resultBadgeClasses(): string
    {
        return match ($this->resultTone()) {
            'success' => 'inline-flex whitespace-nowrap rounded-md bg-emerald-50 px-2.5 py-1 text-xs font-bold text-emerald-700 ring-1 ring-emerald-200',
            'warning' => 'inline-flex whitespace-nowrap rounded-md bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700 ring-1 ring-amber-200',
            'danger' => 'inline-flex whitespace-nowrap rounded-md bg-rose-50 px-2.5 py-1 text-xs font-bold text-rose-700 ring-1 ring-rose-200',
            default => 'inline-flex whitespace-nowrap rounded-md bg-slate-50 px-2.5 py-1 text-xs font-bold text-slate-700 ring-1 ring-slate-200',
        };
    }

    private function resultTone(): string
    {
        return match ($this->resultKey()) {
            'success', 'completed', 'valid', 'verified' => 'success',
            'pending_face', 'pending_checklist', 'profile_incomplete', 'outside_schedule', 'suspicious' => 'warning',
            'invalid', 'failed' => 'danger',
            default => 'neutral',
        };
    }

    private function resultKey(): string
    {
        $result = $this->stringProperty('result');

        if ($result === null && $this->isPatrolRelated()) {
            $result = $this->stringProperty('status');
        }

        if ($result !== null) {
            return Str::of($result)->lower()->replace(' ', '_')->toString();
        }

        $action = Str::lower((string) $this->action);

        if (Str::contains($action, ['blocked', 'outside_schedule'])) {
            return 'outside_schedule';
        }

        if (Str::contains($action, ['suspicious'])) {
            return 'suspicious';
        }

        if (Str::contains($action, ['failed', 'invalid'])) {
            return 'failed';
        }

        if (Str::contains($action, ['completed'])) {
            return 'completed';
        }

        if (Str::contains($action, ['created', 'updated', 'deleted', 'exported', 'submitted', 'login', 'logout', 'marked_read'])) {
            return 'success';
        }

        return 'recorded';
    }

    private function isPatrolRelated(): bool
    {
        return Str::contains((string) $this->action, ['patrol', 'rfid_scan']);
    }

    private function stringProperty(string $key): ?string
    {
        $properties = is_array($this->properties) ? $this->properties : [];
        $value = $properties[$key] ?? null;

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
