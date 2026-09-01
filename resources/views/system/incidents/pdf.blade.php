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

        .narrative-compact {
            min-height: 48px;
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
        <h1 class="report-title">Security incident report</h1>
        <div class="report-subtitle">SLSU Bontoc Patrol incident documentation</div>
    </div>

    <div class="control-line">
        Report no. {{ $reportNumber }} | Generated {{ $generatedAt->format('M d, Y h:i A') }} | Status {{ $status }}
    </div>

    <div class="section">
        <h2 class="section-title">Incident summary</h2>
        <div class="field-row">
            <div class="field">
                <span class="field-label">Category</span>
                <div class="field-value">{{ $category }}</div>
            </div>
            <div class="field">
                <span class="field-label">Priority</span>
                <div class="field-value">{{ $priority }}</div>
            </div>
            <div class="clear"></div>
        </div>
        <div class="field-row">
            <div class="field">
                <span class="field-label">Date/time</span>
                <div class="field-value">{{ $incidentDate }}</div>
            </div>
            <div class="field">
                <span class="field-label">Reported at</span>
                <div class="field-value">{{ $reportedDate }}</div>
            </div>
            <div class="clear"></div>
        </div>
        <div class="field-row">
            <div class="field field-full">
                <span class="field-label">Location</span>
                <div class="field-value">{{ $location }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Reporting details</h2>
        <div class="field-row">
            <div class="field">
                <span class="field-label">Security guard</span>
                <div class="field-value">{{ $guardName }}</div>
            </div>
            <div class="field">
                <span class="field-label">Employee no.</span>
                <div class="field-value">{{ $employeeNo }}</div>
            </div>
            <div class="clear"></div>
        </div>
        <div class="field-row">
            <div class="field">
                <span class="field-label">Checkpoint</span>
                <div class="field-value">{{ $checkpointName }}</div>
            </div>
            <div class="field">
                <span class="field-label">Checkpoint code</span>
                <div class="field-value">{{ $checkpointCode }}</div>
            </div>
            <div class="clear"></div>
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Narrative of incident</h2>
        <div class="narrative">
            {!! nl2br(e($incident->description ?: 'No description provided.')) !!}
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Review and action</h2>
        <div class="field-row">
            <div class="field field-full">
                <span class="field-label">Review notes</span>
                <div class="narrative narrative-compact">{!! nl2br(e($incident->admin_notes ?: 'No admin notes recorded.')) !!}</div>
            </div>
        </div>
        <div class="field-row">
            <div class="field field-full">
                <span class="field-label">Action taken</span>
                <div class="narrative narrative-compact">{!! nl2br(e($incident->action_taken ?: 'No action recorded.')) !!}</div>
            </div>
        </div>
        <div class="field-row">
            <div class="field field-full">
                <span class="field-label">Resolved at</span>
                <div class="field-value">{{ $resolvedDate?->format('M d, Y h:i A') ?? 'Not resolved' }}</div>
            </div>
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Image evidence</h2>
        @if (! empty($imageDataUris))
            @foreach ($imageDataUris as $imageDataUri)
                <div class="evidence">
                    <div class="evidence-caption">Image {{ $loop->iteration }}</div>
                    <img src="{{ $imageDataUri }}" alt="Incident image evidence {{ $loop->iteration }}">
                </div>
            @endforeach
        @else
            <p class="muted">No image evidence attached.</p>
        @endif
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
