<?php

namespace App\Mail;

use App\Models\PatrolLog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class RfidScanIssueMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public PatrolLog $patrolLog)
    {
    }

    public function build(): self
    {
        $status = Str::of($this->patrolLog->status ?: 'scan issue')
            ->replace('_', ' ')
            ->title();

        return $this
            ->subject("RFID Scan Issue: {$status}")
            ->view('emails.rfid-scan-issue');
    }
}
