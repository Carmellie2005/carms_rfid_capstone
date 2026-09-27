<?php

namespace App\Services;

use App\Mail\IncidentReportSubmittedMail;
use App\Mail\RfidScanIssueMail;
use App\Models\IncidentReport;
use App\Models\PatrolLog;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SupervisorEmailNotifier
{
    public function sendIncidentSubmitted(IncidentReport $incidentReport): void
    {
        $incidentReport->loadMissing(['securityGuard', 'checkpoint', 'patrolLog']);

        $this->send(new IncidentReportSubmittedMail($incidentReport));
    }

    public function sendPatrolScanIssue(PatrolLog $patrolLog): void
    {
        if (! in_array($patrolLog->status, ['invalid', 'profile_incomplete', 'outside_schedule', 'suspicious'], true)) {
            return;
        }

        $patrolLog->loadMissing(['securityGuard', 'checkpoint']);

        $this->send(new RfidScanIssueMail($patrolLog));
    }

    private function send(Mailable $mailable): void
    {
        $recipients = $this->recipients();

        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send($mailable);
        } catch (Throwable $exception) {
            Log::warning('Supervisor email alert could not be sent.', [
                'mail' => $mailable::class,
                'recipients' => $recipients,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private function recipients(): array
    {
        $configured = collect(preg_split('/[,;]/', (string) config('mail.supervisor_alert_to')))
            ->map(fn ($email) => trim($email))
            ->filter(fn ($email) => $this->isDeliverableEmail($email))
            ->values();

        if ($configured->isNotEmpty()) {
            return $configured->unique()->all();
        }

        $adminEmails = User::query()
            ->where('role', 'admin')
            ->pluck('email')
            ->filter(fn ($email) => $this->isDeliverableEmail($email))
            ->values();

        if ($adminEmails->isNotEmpty()) {
            return $adminEmails->unique()->all();
        }

        $fallback = config('mail.from.address');

        return $this->isDeliverableEmail($fallback) ? [$fallback] : [];
    }

    private function isDeliverableEmail(?string $email): bool
    {
        if (! $email || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $domain = strtolower((string) strrchr($email, '@'));

        return ! str_ends_with($domain, '.local')
            && ! str_ends_with($domain, '.test');
    }
}
