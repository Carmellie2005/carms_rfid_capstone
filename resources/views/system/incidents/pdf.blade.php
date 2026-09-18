<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Incident Report {{ str_pad((string) $incident->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page {
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #000000;
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 9.5pt;
            line-height: 1.3;
            margin: 0;
        }

        .page {
            height: 936px;
            overflow: hidden;
            page-break-after: always;
            position: relative;
            width: 612px;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .page-background {
            height: 936px;
            left: 0;
            position: absolute;
            top: 0;
            width: 612px;
            z-index: 0;
        }

        .page-title {
            font-size: 12pt;
            font-weight: 700;
            left: 72px;
            letter-spacing: 0;
            position: absolute;
            text-align: center;
            text-transform: uppercase;
            top: 118px;
            width: 468px;
            z-index: 1;
        }

        .field {
            overflow: hidden;
            padding: 7px 9px;
            position: absolute;
            z-index: 1;
        }

        .label {
            display: block;
            font-size: 7.8pt;
            font-weight: 700;
            line-height: 1.15;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .value {
            display: block;
            font-size: 9.5pt;
            font-weight: 400;
            line-height: 1.28;
        }

        .value-small {
            font-size: 8.5pt;
        }

        .person-name {
            font-weight: 700;
        }

        .multiline {
            font-size: 9.2pt;
            line-height: 1.36;
            text-align: justify;
        }

        .note {
            font-size: 8.3pt;
            line-height: 1.28;
        }

        .triple-a {
            height: 59px;
            left: 72px;
            top: 160px;
            width: 156px;
        }

        .triple-b {
            height: 59px;
            left: 228px;
            top: 160px;
            width: 156px;
        }

        .triple-c {
            height: 59px;
            left: 384px;
            top: 160px;
            width: 156px;
        }

        .half-left-row-1 {
            height: 44px;
            left: 72px;
            top: 235px;
            width: 234px;
        }

        .half-right-row-1 {
            height: 44px;
            left: 306px;
            top: 235px;
            width: 234px;
        }

        .half-left-row-2 {
            height: 44px;
            left: 72px;
            top: 279px;
            width: 234px;
        }

        .half-right-row-2 {
            height: 44px;
            left: 306px;
            top: 279px;
            width: 234px;
        }

        .middle-full {
            height: 44px;
            left: 72px;
            top: 323px;
            width: 468px;
        }

        .wide-left {
            height: 45px;
            left: 72px;
            top: 382px;
            width: 234px;
        }

        .wide-right {
            height: 45px;
            left: 306px;
            top: 382px;
            width: 234px;
        }

        .large-box {
            height: 249px;
            left: 72px;
            top: 458px;
            width: 468px;
        }

        .footer-note {
            font-size: 7.8pt;
            left: 72px;
            line-height: 1.25;
            position: absolute;
            top: 715px;
            width: 468px;
            z-index: 1;
        }

        .photo-table {
            border-collapse: collapse;
            margin-top: 5px;
            width: 100%;
        }

        .photo-table td {
            height: 92px;
            padding: 2px;
            text-align: center;
            vertical-align: middle;
            width: 50%;
        }

        .photo-table img {
            max-height: 88px;
            max-width: 100%;
        }

        .empty-evidence {
            font-size: 9pt;
            margin-top: 48px;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $reportNumber = 'IR-'.str_pad((string) $incident->id, 6, '0', STR_PAD_LEFT);
        $priority = ucfirst($incident->priority ?? 'normal');
        $status = str($incident->status ?? 'submitted')->replace('_', ' ')->title();
        $category = $incident->category ?? 'Uncategorized';
        $incidentDate = $incident->incident_at?->timezone(config('app.timezone'))->format('M d, Y h:i A') ?? 'Not recorded';
        $reportedDate = $incident->reported_at?->timezone(config('app.timezone'))->format('M d, Y h:i A') ?? 'Not recorded';
        $resolvedDate = $incident->resolved_at?->timezone(config('app.timezone'));
        $patrol = $incident->patrolLog;
        $guardName = $incident->securityGuard?->name ?? 'Unknown';
        $employeeNo = $incident->securityGuard?->employee_no ?? 'Not recorded';
        $checkpointName = $incident->checkpoint?->name ?? 'Unassigned';
        $checkpointCode = $incident->checkpoint?->code ?? $patrol?->checkpoint_code ?? 'Not recorded';
        $location = $incident->checkpoint?->name ?? $incident->location ?? 'Unassigned';
        $locationCheckpoint = $checkpointCode !== 'Not recorded'
            ? $location.' - '.$checkpointCode
            : $location;
        $reviewNotes = $incident->admin_notes ?: 'No supervisor review notes recorded.';
        $actionTaken = $incident->action_taken ?: 'No action recorded.';
        $resolvedDateLabel = $resolvedDate?->format('M d, Y h:i A') ?? 'Not yet resolved';
        $supervisorName = 'Ryan P. Tomol';
        $evidenceImages = collect($imageDataUris)->take(4)->values();
        $narrative = $incident->description ?: 'No description provided.';
    @endphp

    <section class="page">
        @if ($incidentFormDataUri)
            <img class="page-background" src="{{ $incidentFormDataUri }}" alt="">
        @endif

        <div class="page-title">Security Incident Report</div>

        <div class="field triple-a">
            <span class="label">Report No.</span>
            <span class="value">{{ $reportNumber }}</span>
        </div>
        <div class="field triple-b">
            <span class="label">Status</span>
            <span class="value">{{ $status }}</span>
        </div>
        <div class="field triple-c">
            <span class="label">Generated</span>
            <span class="value value-small">{{ $generatedAt->format('M d, Y h:i A') }}</span>
        </div>

        <div class="field half-left-row-1">
            <span class="label">Category</span>
            <span class="value">{{ $category }}</span>
        </div>
        <div class="field half-right-row-1">
            <span class="label">Priority</span>
            <span class="value">{{ $priority }}</span>
        </div>
        <div class="field half-left-row-2">
            <span class="label">Incident Date / Time</span>
            <span class="value value-small">{{ $incidentDate }}</span>
        </div>
        <div class="field half-right-row-2">
            <span class="label">Reported Date / Time</span>
            <span class="value value-small">{{ $reportedDate }}</span>
        </div>
        <div class="field middle-full">
            <span class="label">Location / Checkpoint</span>
            <span class="value">{{ $locationCheckpoint }}</span>
        </div>
        <div class="field wide-left">
            <span class="label">Reporting Guard</span>
            <span class="value person-name">{{ $guardName }}</span>
        </div>
        <div class="field wide-right">
            <span class="label">Employee No.</span>
            <span class="value">{{ $employeeNo }}</span>
        </div>
        <div class="field large-box">
            <span class="label">Narrative of Incident</span>
            <div class="value multiline">{!! nl2br(e($narrative)) !!}</div>
        </div>
    </section>

    <section class="page">
        @if ($incidentFormDataUri)
            <img class="page-background" src="{{ $incidentFormDataUri }}" alt="">
        @endif

        <div class="page-title">Evidence and Supervisor Review</div>

        <div class="field triple-a">
            <span class="label">Report No.</span>
            <span class="value">{{ $reportNumber }}</span>
        </div>
        <div class="field triple-b">
            <span class="label">Photo Evidence</span>
            <span class="value">{{ count($imageDataUris) }} attached</span>
        </div>
        <div class="field triple-c">
            <span class="label">Status</span>
            <span class="value">{{ $status }}</span>
        </div>

        <div class="field half-left-row-1">
            <span class="label">Review Notes</span>
            <span class="value value-small">{{ $reviewNotes }}</span>
        </div>
        <div class="field half-right-row-1">
            <span class="label">Action Taken</span>
            <span class="value value-small">{{ $actionTaken }}</span>
        </div>
        <div class="field half-left-row-2">
            <span class="label">Resolved Date / Time</span>
            <span class="value value-small">{{ $resolvedDateLabel }}</span>
        </div>
        <div class="field half-right-row-2">
            <span class="label">Security Office</span>
            <span class="value value-small">Security and Safety Office</span>
        </div>
        <div class="field middle-full">
            <span class="label">Evidence Note</span>
            <span class="value value-small">Only incident-related photos are included. Routine checklist photos are not required unless they directly support the incident.</span>
        </div>
        <div class="field wide-left">
            <span class="label">Reporting Guard</span>
            <span class="value person-name">{{ $guardName }}</span>
        </div>
        <div class="field wide-right">
            <span class="label">Supervisor</span>
            <span class="value person-name">{{ $supervisorName }}</span>
        </div>
        <div class="field large-box">
            <span class="label">Evidence (Photo)</span>
            @if ($evidenceImages->isNotEmpty())
                <table class="photo-table">
                    @foreach ($evidenceImages->chunk(2) as $row)
                        <tr>
                            @foreach ($row as $imageDataUri)
                                <td>
                                    <img src="{{ $imageDataUri }}" alt="Incident image evidence {{ $loop->parent->iteration }}-{{ $loop->iteration }}">
                                </td>
                            @endforeach
                            @if ($row->count() === 1)
                                <td></td>
                            @endif
                        </tr>
                    @endforeach
                </table>
            @else
                <div class="empty-evidence">No image evidence attached.</div>
            @endif
        </div>

        @if (count($imageDataUris) > 4)
            <div class="footer-note">
                {{ count($imageDataUris) - 4 }} additional incident photo(s) are stored in the system record.
            </div>
        @endif
    </section>
</body>
</html>
