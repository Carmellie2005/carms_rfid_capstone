<?php

namespace App\Mail;

use App\Models\IncidentReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IncidentReportSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public IncidentReport $incidentReport)
    {
    }

    public function build(): self
    {
        $category = $this->incidentReport->category ?: 'Incident report';
        $priority = ucfirst($this->incidentReport->priority ?: 'normal');

        return $this
            ->subject("{$priority} Incident Report: {$category}")
            ->view('emails.incident-report-submitted');
    }
}
