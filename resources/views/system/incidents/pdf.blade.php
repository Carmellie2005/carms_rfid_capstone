<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Incident Report {{ str_pad((string) $incident->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        @page {
            margin: 200px 58px 135px;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #000000;
            font-family: "Cambria Black", Cambria, Georgia, serif;
            font-size: 11pt;
            line-height: 1.5;
            margin: 0;
        }

        @if (file_exists(public_path('fonts/poppins-regular.woff')))
            @@font-face {
                font-family: "Poppins";
                font-style: normal;
                font-weight: 400;
                src: url("data:font/woff;base64,{{ base64_encode(file_get_contents(public_path('fonts/poppins-regular.woff'))) }}") format("woff");
            }
        @endif

        @if (file_exists(public_path('fonts/cambria-bold.ttf')))
            @@font-face {
                font-family: "Cambria Black";
                font-style: normal;
                font-weight: 900;
                src: url("data:font/truetype;base64,{{ base64_encode(file_get_contents(public_path('fonts/cambria-bold.ttf'))) }}") format("truetype");
            }
        @endif

        .letterhead-page {
            height: 1123px;
            left: -58px;
            position: fixed;
            top: -200px;
            width: 795px;
            z-index: -1000;
        }

        .core-values {
            color: #000000;
            font-family: "Poppins", DejaVu Sans, sans-serif;
            font-size: 8.5px;
            font-weight: 400;
            left: 38px;
            line-height: 1.2;
            position: fixed;
            right: 0;
            top: -39px;
            white-space: nowrap;
        }

        .document-title {
            border-bottom: 1px solid #000000;
            margin-bottom: 12px;
            padding-bottom: 8px;
            text-align: center;
        }

        .report-title {
            color: #000000;
            font-family: "Cambria Black", Cambria, Georgia, serif;
            font-size: 11pt;
            font-weight: 900;
            margin: 0;
        }

        .report-subtitle {
            color: #000000;
            font-size: 11pt;
            margin-top: 2px;
        }

        .control-line {
            margin-bottom: 14px;
            text-align: center;
        }

        .control-table,
        .report-table {
            border-collapse: collapse;
            margin-bottom: 12px;
            width: 100%;
        }

        .control-table td,
        .report-table td {
            border: 1px solid #000000;
            color: #000000;
            min-height: 44px;
            padding: 7px 8px 8px;
            vertical-align: top;
        }

        .control-table td {
            width: 33.333%;
        }

        .report-table .half {
            width: 50%;
        }

        .field-label-boxed {
            color: #000000;
            display: block;
            font-size: 9pt;
            font-weight: 700;
            margin-bottom: 4px;
            text-transform: uppercase;
        }

        .field-value-boxed {
            color: #000000;
            font-size: 11pt;
            font-weight: 400;
            min-height: 18px;
        }

        .section {
            margin-top: 15px;
            page-break-inside: avoid;
        }

        .section-title {
            border-bottom: 1px solid #000000;
            color: #000000;
            font-size: 11pt;
            font-weight: 700;
            margin: 0 0 8px;
            padding-bottom: 2px;
            text-transform: none;
        }

        .field-row {
            clear: both;
            margin-bottom: 8px;
        }

        .field {
            float: left;
            width: 48%;
        }

        .field + .field {
            margin-left: 4%;
        }

        .field-full {
            float: none;
            width: 100%;
        }

        .field-label {
            color: #000000;
            display: block;
            font-size: 11pt;
            font-weight: 700;
            margin-bottom: 1px;
        }

        .field-value {
            border-bottom: 1px solid #000000;
            color: #000000;
            font-size: 11pt;
            min-height: 20px;
            padding: 0 3px 2px;
        }

        .clear {
            clear: both;
        }

        .narrative {
            border-bottom: 1px solid #000000;
            color: #000000;
            font-size: 11pt;
            line-height: 1.55;
            min-height: 88px;
            padding: 2px 3px 10px;
            white-space: normal;
        }

        .narrative-box {
            border: 1px solid #000000;
            color: #000000;
            font-size: 11pt;
            line-height: 1.55;
            min-height: 210px;
            padding: 10px 12px;
            text-align: justify;
            white-space: normal;
        }

        .narrative-compact {
            min-height: 48px;
        }

        .review-box {
            min-height: 58px;
        }

        .secondary-page {
            page-break-before: always;
        }

        .evidence {
            border: 1px solid #000000;
            color: #000000;
            font-size: 11pt;
            margin-bottom: 10px;
            min-height: 165px;
            padding: 8px;
            text-align: center;
            page-break-inside: avoid;
        }

        .evidence img {
            height: auto;
            max-height: 240px;
            max-width: 100%;
        }

        .evidence-caption {
            font-size: 9pt;
            font-weight: 700;
            margin-bottom: 5px;
            text-align: left;
        }

        .evidence-note {
            color: #000000;
            font-size: 9pt;
            margin-top: 6px;
            text-align: left;
        }

        .muted {
            color: #000000;
            font-size: 11pt;
        }

        .signature-row {
            margin-top: 46px;
        }

        .signature {
            float: left;
            text-align: center;
            width: 30%;
        }

        .signature + .signature {
            margin-left: 5%;
        }

        .signature-line {
            border-top: 1px solid #000000;
            color: #000000;
            font-size: 11pt;
            font-weight: 700;
            padding-top: 5px;
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
        $supervisorOffice = 'Safety and Security Services';
    @endphp

    @if ($letterheadDataUri)
        <img class="letterhead-page" src="{{ $letterheadDataUri }}" alt="">
        <div class="core-values">
            Excellence | Service | Leadership and Good Governance | Innovation | Social Responsibility | Integrity | Professionalism | Spirituality
        </div>
    @endif

    <div class="document-title">
        <h1 class="report-title">Security Incident Report</h1>
        <div class="report-subtitle">Incident Documentation for Checkpoint Patrol Monitoring</div>
    </div>

    <table class="control-table">
        <tr>
            <td>
                <span class="field-label-boxed">Report No.</span>
                <div class="field-value-boxed">{{ $reportNumber }}</div>
            </td>
            <td>
                <span class="field-label-boxed">Status</span>
                <div class="field-value-boxed">{{ $status }}</div>
            </td>
            <td>
                <span class="field-label-boxed">Generated</span>
                <div class="field-value-boxed">{{ $generatedAt->format('M d, Y h:i A') }}</div>
            </td>
        </tr>
    </table>

    <div class="section">
        <h2 class="section-title">Incident Summary</h2>
        <table class="report-table">
            <tr>
                <td class="half">
                    <span class="field-label-boxed">Category</span>
                    <div class="field-value-boxed">{{ $category }}</div>
                </td>
                <td class="half">
                    <span class="field-label-boxed">Priority</span>
                    <div class="field-value-boxed">{{ $priority }}</div>
                </td>
            </tr>
            <tr>
                <td class="half">
                    <span class="field-label-boxed">Incident Date / Time</span>
                    <div class="field-value-boxed">{{ $incidentDate }}</div>
                </td>
                <td class="half">
                    <span class="field-label-boxed">Reported Date / Time</span>
                    <div class="field-value-boxed">{{ $reportedDate }}</div>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="field-label-boxed">Location / Checkpoint</span>
                    <div class="field-value-boxed">{{ $locationCheckpoint }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Reporting Guard</h2>
        <table class="report-table">
            <tr>
                <td class="half">
                    <span class="field-label-boxed">Security Guard</span>
                    <div class="field-value-boxed">{{ $guardName }}</div>
                </td>
                <td class="half">
                    <span class="field-label-boxed">Employee No.</span>
                    <div class="field-value-boxed">{{ $employeeNo }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Narrative of Incident</h2>
        <div class="narrative-box">
            {!! nl2br(e($incident->description ?: 'No description provided.')) !!}
        </div>
    </div>

    <div class="secondary-page"></div>

    <div class="document-title">
        <h1 class="report-title">Security Incident Report</h1>
        <div class="report-subtitle">Evidence, Review, and Certification</div>
    </div>

    <div class="section">
        <h2 class="section-title">Evidence</h2>
        @if (! empty($imageDataUris))
            @foreach ($imageDataUris as $imageDataUri)
                <div class="evidence">
                    <div class="evidence-caption">Incident Photo Evidence {{ $loop->iteration }}</div>
                    <img src="{{ $imageDataUri }}" alt="Incident image evidence {{ $loop->iteration }}">
                </div>
            @endforeach
        @else
            <div class="evidence">
                <div class="evidence-caption">Incident Photo Evidence</div>
                <p class="muted">No image evidence attached.</p>
            </div>
        @endif
        <div class="evidence-note">
            Only incident-related photos should be included. Checklist proof photos are not required here unless they directly support the incident.
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Supervisor Review and Action</h2>
        <table class="report-table">
            <tr>
                <td>
                    <span class="field-label-boxed">Review Notes</span>
                    <div class="review-box">{!! nl2br(e($reviewNotes)) !!}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="field-label-boxed">Action Taken</span>
                    <div class="review-box">{!! nl2br(e($actionTaken)) !!}</div>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="field-label-boxed">Resolved Date / Time</span>
                    <div class="field-value-boxed">{{ $resolvedDateLabel }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Certification</h2>
        <div class="signature-row">
            <div class="signature">
                <div class="signature-line">{{ $guardName }}</div>
                Reporting security guard
            </div>
            <div class="signature">
                <div class="signature-line">{{ $supervisorName }}</div>
                Head, {{ $supervisorOffice }}
            </div>
            <div class="signature">
                <div class="signature-line">{{ $resolvedDate?->format('M d, Y') ?? '' }}</div>
                Date reviewed
            </div>
            <div class="clear"></div>
        </div>
    </div>
</body>
</html>
