<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Patrol Logs Report</title>
    <style>
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
            margin: {{ $letterheadDataUri ? '170px 42px 68px' : '42px' }};
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #111827;
            font-family: "Calibri", "DejaVu Sans", sans-serif;
            font-size: 7.8pt;
            line-height: 1.25;
            margin: 0;
        }

        .letterhead-page {
            height: 1123px;
            left: -42px;
            position: fixed;
            top: -170px;
            width: 795px;
            z-index: -1000;
        }

        .footer-note {
            bottom: -46px;
            color: #374151;
            font-size: 7pt;
            left: 0;
            position: fixed;
            right: 0;
            text-align: center;
        }

        .title-block {
            border-bottom: 1.2px solid #111827;
            margin-bottom: 11px;
            padding-bottom: 7px;
            text-align: center;
        }

        .kicker {
            color: #1d4ed8;
            font-size: 7.3pt;
            font-weight: 700;
            letter-spacing: 0.06em;
            margin-bottom: 3px;
            text-transform: uppercase;
        }

        h1 {
            font-size: 13pt;
            margin: 0;
            text-transform: uppercase;
        }

        .subtitle {
            color: #374151;
            font-size: 8.2pt;
            margin-top: 3px;
        }

        .section {
            margin-top: 11px;
            page-break-inside: avoid;
        }

        .section-title {
            color: #111827;
            font-size: 8.5pt;
            font-weight: 700;
            letter-spacing: 0.03em;
            margin: 0 0 5px;
            text-transform: uppercase;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #1f2937;
            padding: 4px 5px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        th {
            background: #eff6ff;
            color: #1e40af;
            font-size: 6.9pt;
            font-weight: 700;
            text-transform: uppercase;
        }

        .meta th {
            width: 15%;
        }

        .summary th,
        .summary td {
            text-align: center;
        }

        .summary td {
            font-size: 11pt;
            font-weight: 700;
        }

        .w-no {
            width: 4%;
        }

        .w-time {
            width: 13%;
        }

        .w-guard {
            width: 15%;
        }

        .w-checkpoint {
            width: 15%;
        }

        .w-rfid {
            width: 11%;
        }

        .w-result {
            width: 13%;
        }

        .w-evidence {
            width: 18%;
        }

        .w-incident {
            width: 11%;
        }

        .muted {
            color: #4b5563;
        }

        .mono {
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 7.1pt;
        }

        .strong {
            font-weight: 700;
        }

        .badge {
            border: 1px solid #94a3b8;
            border-radius: 10px;
            display: inline-block;
            font-size: 6.7pt;
            font-weight: 700;
            padding: 2px 6px;
        }

        .badge-valid {
            background: #ecfdf5;
            border-color: #6ee7b7;
            color: #047857;
        }

        .badge-warning {
            background: #fffbeb;
            border-color: #fbbf24;
            color: #92400e;
        }

        .badge-danger {
            background: #fef2f2;
            border-color: #fca5a5;
            color: #b91c1c;
        }

        .badge-info {
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1d4ed8;
        }

        .note-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            color: #334155;
            font-size: 7.2pt;
            padding: 6px 8px;
        }

        .signature-row {
            margin-top: 30px;
        }

        .signature {
            float: left;
            text-align: center;
            width: 45%;
        }

        .signature + .signature {
            margin-left: 10%;
        }

        .signature-line {
            border-top: 1px solid #111827;
            font-weight: 700;
            padding-top: 5px;
        }

        .clear {
            clear: both;
        }
    </style>
</head>
<body>
    @php
        $statusLabel = function (?string $status): string {
            if ($status === 'pending_face') {
                return 'Pending Selfie';
            }

            return str($status ?: 'unknown')->replace('_', ' ')->title()->toString();
        };

        $statusClass = function (?string $status): string {
            return match ($status) {
                'valid' => 'badge-valid',
                'invalid', 'profile_incomplete', 'expired' => 'badge-danger',
                'suspicious', 'outside_schedule' => 'badge-warning',
                default => 'badge-info',
            };
        };

        $hasAreaSelfie = fn ($log): bool => filled($log->area_selfie_path) || filled($log->area_selfie_image_data);
    @endphp

    @if ($letterheadDataUri)
        <img class="letterhead-page" src="{{ $letterheadDataUri }}" alt="">
    @endif

    <div class="footer-note">
        SLSU Bontoc Patrol - Patrol Logs Report - Generated {{ $generatedAt->format('M d, Y h:i A') }}
    </div>

    <div class="title-block">
        <div class="kicker">{{ $isSupervisor ? 'Supervisor Record' : 'Guard Record' }}</div>
        <h1>{{ $isSupervisor ? 'Patrol Logs Report' : 'My Patrol Logs Report' }}</h1>
        <div class="subtitle">RFID checkpoint scans, required area selfie proof, checklist results, and related incident records</div>
    </div>

    <div class="section">
        <h2 class="section-title">Report Scope</h2>
        <table class="meta">
            <tr>
                <th>Generated</th>
                <td>{{ $generatedAt->format('M d, Y h:i A') }}</td>
                <th>Period</th>
                <td>{{ $reportPeriod }}</td>
            </tr>
            <tr>
                <th>Guard</th>
                <td>{{ $filters['guard'] }}</td>
                <th>Status</th>
                <td>{{ $filters['status'] }}</td>
            </tr>
            <tr>
                <th>Checkpoint</th>
                <td>{{ $filters['checkpoint'] }}</td>
                <th>Date Filter</th>
                <td>{{ $filters['date'] }}</td>
            </tr>
            <tr>
                <th>Records</th>
                <td colspan="3">{{ $summary['total'] }} shown{{ $summary['total'] >= $recordLimit ? ' (limited to latest '.$recordLimit.' records)' : '' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Summary</h2>
        <table class="summary">
            <tr>
                <th>Total</th>
                <th>Valid</th>
                <th>Suspicious</th>
                <th>Invalid</th>
                <th>Pending Selfie</th>
                <th>Pending Checklist</th>
                <th>With Selfie</th>
                <th>With Checklist</th>
                <th>Incidents</th>
            </tr>
            <tr>
                <td>{{ $summary['total'] }}</td>
                <td>{{ $summary['valid'] }}</td>
                <td>{{ $summary['suspicious'] }}</td>
                <td>{{ $summary['invalid'] }}</td>
                <td>{{ $summary['pending_selfie'] }}</td>
                <td>{{ $summary['pending_checklist'] }}</td>
                <td>{{ $summary['with_area_selfie'] }}</td>
                <td>{{ $summary['with_checklist'] }}</td>
                <td>{{ $summary['incidents'] }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="note-box">
            Routine patrol proof is recorded through the required area selfie. Checklist proof photos are counted only when an item needs supporting documentation, while incident photos remain part of the incident report PDF.
        </div>
    </div>

    <div class="section">
        <h2 class="section-title">Log Records</h2>
        <table>
            <thead>
                <tr>
                    <th class="w-no">No.</th>
                    <th class="w-time">Date / Time</th>
                    <th class="w-guard">Guard</th>
                    <th class="w-checkpoint">Checkpoint</th>
                    <th class="w-rfid">RFID UID</th>
                    <th class="w-result">Result</th>
                    <th class="w-evidence">Checklist / Evidence</th>
                    <th class="w-incident">Incident</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $checklist = $log->checklistResponse;
                        $items = \App\Support\PatrolChecklist::statusSummaries($checklist);
                        $issueItems = $items->where('status', \App\Support\PatrolChecklist::STATUS_ISSUE)->pluck('label');
                        $normalCount = $items->where('status', \App\Support\PatrolChecklist::STATUS_NORMAL)->count();
                        $scanTime = $log->scanned_at?->timezone(config('app.timezone'));
                        $capturedAt = $log->area_selfie_captured_at?->timezone(config('app.timezone'));
                    @endphp
                    <tr>
                        <td class="mono">{{ $loop->iteration }}</td>
                        <td>
                            <span class="strong">{{ $scanTime?->format('M d, Y') ?? 'Not recorded' }}</span>
                            <br><span class="muted">{{ $scanTime?->format('h:i A') ?? '' }}</span>
                        </td>
                        <td>
                            <span class="strong">{{ $log->securityGuard?->name ?? 'Unknown' }}</span>
                            <br><span class="muted mono">{{ $log->securityGuard?->employee_no ?? 'No guard match' }}</span>
                        </td>
                        <td>
                            <span class="strong">{{ $log->checkpoint?->name ?? 'Unknown' }}</span>
                            <br><span class="muted mono">{{ $log->checkpoint?->code ?? $log->checkpoint_code ?? 'No code' }}</span>
                        </td>
                        <td><span class="mono">{{ $log->rfid_uid ?: 'Not recorded' }}</span></td>
                        <td>
                            <span class="badge {{ $statusClass($log->status) }}">{{ $statusLabel($log->status) }}</span>
                            @if ($log->notes)
                                <br><span class="muted">{{ str($log->notes)->limit(70) }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="strong">{{ $log->checklistSummary() }}</span>
                            @if ($checklist)
                                <br><span class="muted">
                                    @if ($issueItems->isNotEmpty())
                                        Issue: {{ $issueItems->implode(', ') }}
                                    @else
                                        {{ $normalCount }} normal {{ str('item')->plural($normalCount) }}
                                    @endif
                                </span>
                                @if ($checklist->remarks)
                                    <br><span class="muted">Remarks: {{ str($checklist->remarks)->limit(60) }}</span>
                                @endif
                            @endif
                            <br><span class="muted">Area selfie: {{ $hasAreaSelfie($log) ? 'Captured' : 'Not captured' }}</span>
                            @if ($capturedAt)
                                <br><span class="muted">Photo time: {{ $capturedAt->format('M d, Y h:i A') }}</span>
                            @endif
                            @if ($log->checklistPhotoCount() > 0)
                                <br><span class="muted">Checklist photos: {{ $log->checklistPhotoCount() }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($log->incidentReport)
                                <span class="strong">IR-{{ str_pad((string) $log->incidentReport->id, 6, '0', STR_PAD_LEFT) }}</span>
                                <br>{{ $log->incidentReport->category }}
                                <br><span class="muted">{{ str($log->incidentReport->status)->replace('_', ' ')->title() }}</span>
                            @else
                                None
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="muted">No patrol logs found for this report scope.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="signature-row">
        <div class="signature">
            <div class="signature-line">{{ auth()->user()?->name ?? 'System User' }}</div>
            Prepared by
        </div>
        <div class="signature">
            <div class="signature-line">&nbsp;</div>
            Reviewed by
        </div>
        <div class="clear"></div>
    </div>
</body>
</html>
