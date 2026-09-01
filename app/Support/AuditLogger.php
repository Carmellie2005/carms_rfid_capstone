<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class AuditLogger
{
    public static function record(string $action, string $description, ?Model $subject = null, array $properties = []): void
    {
        $request = request();
        $user = Auth::user();

        AuditLog::create([
            'user_id' => $user?->id,
            'actor_name' => $user?->name ?? ($properties['actor_name'] ?? 'System'),
            'action' => Str::of($action)->lower()->replace(' ', '_')->toString(),
            'description' => $description,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? Str::limit((string) $request->userAgent(), 255, '') : null,
        ]);
    }
}
