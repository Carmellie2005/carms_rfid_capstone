<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Patrol Log {{ str_pad((string) $patrolLog->id, 6, '0', STR_PAD_LEFT) }}</title>
    <style>
        @font-face {
            font-family: "Poppins";
            font-style: normal;
            font-weight: 400;
            src: url("{{ 'file:///'.str_replace('\\', '/', public_path('fonts/poppins-regular.ttf')) }}") format("truetype");
        }

        @font-face {
            font-family: "Calibri";
            font-style: normal;
            font-weight: 400;
            src: url("{{ 'file:///'.str_replace('\\', '/', storage_path('fonts/calibri_normal_9a5a9d05ec04a6ad109cf6dc929a5838.ttf')) }}") format("truetype");
        }

        @font-face {
            font-family: "Calibri";
            font-style: normal;
            font-weight: 700;
            src: url("{{ 'file:///'.str_replace('\\', '/', storage_path('fonts/calibri_bold_606836eb88dfcf370258af3515c0027b.ttf')) }}") format("truetype");
        }

        @page {
            margin: 0;
            size: 595.28pt 841.89pt;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #000000;
            font-family: "Calibri", "DejaVu Sans", sans-serif;
            font-size: 11pt;
            letter-spacing: 0;
            line-height: 1.2;
            margin: 0;
            word-spacing: 0;
        }

        .page {
            height: 841.89pt;
            overflow: hidden;
            page-break-after: always;
            position: relative;
            width: 595.28pt;
        }

        .page:last-child {
            page-break-after: auto;
        }

        .core-values {
            font-family: "Poppins", "Calibri", "DejaVu Sans", sans-serif;
            font-size: 7pt;
            font-weight: 400;
            left: 34pt;
            letter-spacing: -0.01em;
            line-height: 1;
            position: absolute;
            text-align: center;
            top: 116pt;
            white-space: nowrap;
            width: 544pt;
            z-index: 1;
        }

        .letterhead {
            height: 90pt;
            left: 133pt;
            position: absolute;
            top: 22pt;
            width: 260pt;
            z-index: 1;
        }

        .campus-meta {
            font-family: "Poppins", "Calibri", "DejaVu Sans", sans-serif;
            font-size: 6.3pt;
            left: 208pt;
            line-height: 1.15;
            position: absolute;
            top: 78pt;
            width: 230pt;
            z-index: 2;
        }

        .campus-meta a {
            color: #003f9f;
            text-decoration: underline;
        }

        .bagong {
            height: 62.4pt;
            left: 400pt;
            position: absolute;
            top: 31pt;
            width: 59.8pt;
            z-index: 1;
        }

        .header-rule {
            border-top: 1pt solid #111111;
            left: 52pt;
            position: absolute;
            top: 129pt;
            width: 508pt;
            z-index: 1;
        }

        .footer-rule {
            border-top: 1pt solid #111111;
            bottom: 102pt;
            left: 52pt;
            position: absolute;
            width: 508pt;
            z-index: 1;
        }

        .footer-qs {
            bottom: 28pt;
            height: 62pt;
            left: 341pt;
            position: absolute;
            width: 62pt;
            z-index: 1;
        }

        .footer-socotec {
            bottom: 29pt;
            height: 60pt;
            left: 429pt;
            position: absolute;
            width: 107pt;
            z-index: 1;
        }

        .report-title {
            font-size: 11pt;
            font-weight: 700;
            left: 0;
            position: absolute;
            text-align: center;
            top: 143pt;
            width: 595.28pt;
            z-index: 1;
        }

        .report-subtitle {
            font-size: 11pt;
            font-weight: 400;
            left: 0;
            position: absolute;
            text-align: center;
            top: 158pt;
            width: 595.28pt;
            z-index: 1;
        }

        .report-table,
        .details-table,
        .checklist-table,
        .review-table,
        .signature-table {
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-table,
        .details-table,
        .checklist-table,
        .review-table {
            left: 72.5pt;
            position: absolute;
            width: 467.21pt;
            z-index: 1;
        }

        .report-table {
            top: 186pt;
        }

        .details-table {
            top: 256pt;
        }

        .report-table td,
        .details-table td,
        .checklist-table td,
        .review-table td {
            border: 0.75pt solid #111111;
            font-size: 11pt;
            line-height: 1.25;
            padding: 5pt 7pt 6pt;
            vertical-align: top;
        }

        .report-table td {
            height: 58pt;
            width: 33.333%;
        }

        .details-table td {
            font-size: 10.5pt;
            height: 30pt;
            line-height: 1.15;
            padding: 3pt 6pt 4pt;
        }

        .details-table .label {
            margin-bottom: 2pt;
        }

        .label {
            display: block;
            font-weight: 700;
            margin-bottom: 3pt;
        }

        .value {
            display: block;
            font-weight: 400;
        }

        .checklist-label,
        .notes-label,
        .documentation-label {
            font-size: 11pt;
            font-weight: 700;
            left: 72.5pt;
            position: absolute;
            width: 467.21pt;
            z-index: 1;
        }

        .checklist-label {
            top: 460pt;
        }

        .checklist-table {
            top: 482pt;
        }

        .checklist-table td {
            font-size: 9.6pt;
            height: 18pt;
            line-height: 1.1;
            padding: 2pt 6pt;
            vertical-align: middle;
        }

        .checklist-table .status-cell {
            text-align: center;
            width: 96pt;
        }

        .notes-label {
            top: 590pt;
        }

        .notes-body {
            font-size: 10.5pt;
            left: 72.5pt;
            line-height: 1.25;
            position: absolute;
            text-align: justify;
            top: 611pt;
            width: 467.21pt;
            z-index: 1;
        }

        .documentation-label {
            top: 164pt;
        }

        .documentation-photo {
            height: 255pt;
            left: 127.5pt;
            object-fit: cover;
            position: absolute;
            width: 340pt;
            z-index: 1;
        }

        .documentation-photo-slot-1 {
            top: 190pt;
        }

        .documentation-photo-slot-2 {
            top: 464pt;
        }

        .documentation-empty {
            font-size: 11pt;
            left: 72.5pt;
            position: absolute;
            text-align: center;
            top: 239pt;
            width: 467.21pt;
            z-index: 1;
        }

        .review-table {
            top: 164pt;
        }

        .review-table td {
            height: 48pt;
            width: 100%;
        }

        .review-table .value {
            line-height: 1.3;
            text-align: justify;
        }

        .signature-table {
            left: 72.5pt;
            position: absolute;
            table-layout: fixed;
            top: 369pt;
            width: 467.21pt;
            z-index: 1;
        }

        .signature-table td {
            border: 0;
            font-size: 11pt;
            line-height: 1.15;
            padding: 0 8pt;
            text-align: center;
            vertical-align: top;
            width: 50%;
        }

        .signature-name {
            display: block;
            font-weight: 700;
            margin-bottom: 3pt;
        }

        .signature-label,
        .office-label {
            display: block;
            font-weight: 400;
        }
    </style>
</head>
<body>
    @php
        $reportNumber = 'PL-'.str_pad((string) $patrolLog->id, 6, '0', STR_PAD_LEFT);
        $status = str($patrolLog->status ?? 'valid')->replace('_', ' ')->title();
        $rfidStatus = str($patrolLog->rfid_status ?? 'not recorded')->replace('_', ' ')->title();
        $scanDate = $patrolLog->scanned_at?->timezone(config('app.timezone'))->format('M d, Y h:i A') ?? 'Not recorded';
        $selfieDate = $patrolLog->area_selfie_captured_at?->timezone(config('app.timezone'))->format('M d, Y h:i A') ?? 'Not recorded';
        $guardName = $patrolLog->securityGuard?->name ?? 'Unknown';
        $employeeNo = $patrolLog->securityGuard?->employee_no ?? 'Not recorded';
        $guardShift = $patrolLog->securityGuard?->shift ?? 'Not recorded';
        $checkpointCode = $patrolLog->checkpoint?->code ?? $patrolLog->checkpoint_code ?? 'Not recorded';
        $checkpointName = $patrolLog->checkpoint?->name ?? 'Unknown checkpoint';
        $checkpointLocation = $patrolLog->checkpoint?->location ?: $checkpointName;
        $locationCheckpoint = $checkpointCode !== 'Not recorded'
            ? $checkpointLocation.' - '.$checkpointCode
            : $checkpointLocation;
        $coordinates = ($patrolLog->area_selfie_latitude && $patrolLog->area_selfie_longitude)
            ? number_format((float) $patrolLog->area_selfie_latitude, 6).', '.number_format((float) $patrolLog->area_selfie_longitude, 6)
            : 'Not recorded';
        $accuracy = $patrolLog->area_selfie_accuracy
            ? number_format((float) $patrolLog->area_selfie_accuracy, 2).' meters'
            : 'Not recorded';
        $checklistItems = \App\Support\PatrolChecklist::statusSummaries($patrolLog->checklistResponse);
        $checklistSummary = $patrolLog->checklistSummary();
        $remarks = $patrolLog->checklistResponse?->remarks ?: ($patrolLog->notes ?: 'No remarks recorded.');
        $attachedIncident = $patrolLog->incidentReport
            ? ($patrolLog->incidentReport->category ?? 'Incident report').' - '.str($patrolLog->incidentReport->status ?? 'submitted')->replace('_', ' ')->title()
            : 'No incident report attached.';
        $supervisorName = 'Ryan P. Tomol';
        $documentationImages = collect($imageDataUris)->values();
        $letterheadSrc = 'file:///'.str_replace('\\', '/', public_path('images/pdf-template/slsu-letterhead.png'));
        $bagongSrc = 'file:///'.str_replace('\\', '/', public_path('images/pdf-template/bagong-pilipinas.png'));
        $qsSrc = 'file:///'.str_replace('\\', '/', public_path('images/pdf-template/qs-rated-good.png'));
        $socotecSrc = 'file:///'.str_replace('\\', '/', public_path('images/pdf-template/socotec-iso9001.jpg'));
    @endphp

    @php
        $pageChrome = function () use ($letterheadSrc, $bagongSrc, $qsSrc, $socotecSrc): string {
            return '
                <div class="core-values">Excellence | Service | Leadership and Good Governance | Innovation | Social Responsibility | Integrity | Professionalism | Spirituality</div>
                <img class="letterhead" src="'.e($letterheadSrc).'" alt="">
                <div class="campus-meta">
                    Bontoc Campus, San Ramon, Bontoc, Southern Leyte<br>
                    Email: <a href="mailto:cd_bt@southernleytestateu.edu.ph">cd_bt@southernleytestateu.edu.ph</a><br>
                    Website: www.southernleytestateu.edu.ph
                </div>
                <img class="bagong" src="'.e($bagongSrc).'" alt="">
                <div class="header-rule"></div>
                <div class="footer-rule"></div>
                <img class="footer-qs" src="'.e($qsSrc).'" alt="">
                <img class="footer-socotec" src="'.e($socotecSrc).'" alt="">
            ';
        };
    @endphp

    <section class="page">
        {!! $pageChrome() !!}

        <div class="report-title">Security Patrol Log Report</div>
        <div class="report-subtitle">Checkpoint Patrol Log Documentation</div>

        <table class="report-table">
            <tr>
                <td>
                    <span class="label">Patrol Log No.</span>
                    <span class="value">{{ $reportNumber }}</span>
                </td>
                <td>
                    <span class="label">Status:</span>
                    <span class="value">{{ $status }}</span>
                </td>
                <td>
                    <span class="label">Generated:</span>
                    <span class="value">{{ $generatedAt->format('M d, Y h:i A') }}</span>
                </td>
            </tr>
        </table>

        <table class="details-table">
            <tr>
                <td colspan="2">
                    <span class="label">Location / Checkpoint:</span>
                    <span class="value">{{ $locationCheckpoint }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Security Guard:</span>
                    <span class="value">{{ $guardName }}</span>
                </td>
                <td>
                    <span class="label">Employee No.</span>
                    <span class="value">{{ $employeeNo }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">RFID UID:</span>
                    <span class="value">{{ $patrolLog->rfid_uid ?? 'Not recorded' }}</span>
                </td>
                <td>
                    <span class="label">RFID Status:</span>
                    <span class="value">{{ $rfidStatus }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Guard Shift:</span>
                    <span class="value">{{ $guardShift }}</span>
                </td>
                <td>
                    <span class="label">Patrol Date / Time:</span>
                    <span class="value">{{ $scanDate }}</span>
                </td>
            </tr>
            <tr>
                <td colspan="2">
                    <span class="label">Area Selfie Captured:</span>
                    <span class="value">{{ $selfieDate }}</span>
                </td>
            </tr>
        </table>

        <div class="checklist-label">Patrol Checklist:</div>
        <table class="checklist-table">
            @forelse ($checklistItems as $item)
                <tr>
                    <td>{{ $item['label'] }}</td>
                    <td class="status-cell">{{ $item['status_label'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="2">No checklist status recorded.</td>
                </tr>
            @endforelse
        </table>

        <div class="notes-label">Remarks / Notes:</div>
        <div class="notes-body">{!! nl2br(e($remarks)) !!}</div>
    </section>

    @forelse ($documentationImages->chunk(2) as $imagePair)
        <section class="page">
            {!! $pageChrome() !!}
            <div class="documentation-label">Documentation</div>
            @foreach ($imagePair->values() as $imageDataUri)
                <img
                    class="documentation-photo documentation-photo-slot-{{ $loop->iteration }}"
                    src="{{ $imageDataUri }}"
                    alt="Patrol documentation image {{ (($loop->parent->iteration - 1) * 2) + $loop->iteration }}"
                >
            @endforeach
        </section>
    @empty
        <section class="page">
            {!! $pageChrome() !!}
            <div class="documentation-label">Documentation</div>
            <div class="documentation-empty">No patrol documentation image attached.</div>
        </section>
    @endforelse

    <section class="page">
        {!! $pageChrome() !!}

        <table class="review-table">
            <tr>
                <td>
                    <span class="label">Checklist Summary:</span>
                    <span class="value">{{ $checklistSummary }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Location Coordinates:</span>
                    <span class="value">{{ $coordinates }} | Accuracy: {{ $accuracy }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Linked Incident Report:</span>
                    <span class="value">{{ $attachedIncident }}</span>
                </td>
            </tr>
        </table>

        <table class="signature-table">
            <tr>
                <td>
                    <span class="signature-name">{{ $guardName }}</span>
                    <span class="signature-label">Reporting Guard</span>
                </td>
                <td>
                    <span class="signature-name">{{ $supervisorName }}</span>
                    <span class="signature-label">Supervisor</span>
                    <span class="office-label">Security and Safety Office</span>
                </td>
            </tr>
        </table>
    </section>
</body>
</html>
