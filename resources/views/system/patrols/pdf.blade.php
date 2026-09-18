<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Patrol Logs Report</title>
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
            margin: {{ $letterheadDataUri ? '178px 52px 96px' : '52px' }};
            size: A4 portrait;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #000000;
            font-family: "Calibri", "DejaVu Sans", sans-serif;
            font-size: 8pt;
            letter-spacing: 0;
            line-height: 1.25;
            margin: 0;
            word-spacing: 0;
        }

        .letterhead-page {
            height: 1123px;
            left: -52px;
            position: fixed;
            top: -178px;
            width: 795px;
            z-index: -1000;
        }

        .core-values {
            font-family: "Poppins", "Calibri", "DejaVu Sans", sans-serif;
            font-size: 5.8pt;
            left: -8px;
            letter-spacing: 0;
            line-height: 1;
            position: fixed;
            right: -8px;
            text-align: center;
            top: -58px;
            white-space: nowrap;
        }

        .report-title {
            margin: 0 0 14px;
            text-align: center;
        }

        .report-title h1 {
            font-size: 12pt;
            font-weight: 700;
            margin: 0;
            text-transform: uppercase;
        }

        .report-title p {
            font-size: 8pt;
            margin: 3px 0 0;
        }

        .section {
            margin-top: 13px;
            page-break-inside: avoid;
        }

        .records-section {
            margin-top: 13px;
        }

        .section-title {
            color: #000000;
            font-size: 8.4pt;
            font-weight: 700;
            margin: 0 0 5px;
            text-transform: uppercase;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        th,
        td {
            border: 0.8pt solid #1d4ed8;
            padding: 5px 6px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        th {
            background: #eaf2ff;
            color: #0f3d91;
            font-size: 7pt;
            font-weight: 700;
            text-transform: uppercase;
        }

        td {
            background: #ffffff;
        }

        .meta th {
            width: 17%;
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
            width: 16%;
        }

        .w-checkpoint {
            width: 15%;
        }

        .w-rfid {
            width: 12%;
        }

        .w-result {
            width: 13%;
        }

        .w-checklist {
            width: 18%;
        }

        .w-incident {
            width: 9%;
        }

        .strong {
            font-weight: 700;
        }

        .muted {
            color: #333333;
        }

        .mono {
            font-family: "DejaVu Sans Mono", monospace;
            font-size: 7.2pt;
        }

        .policy td:first-child {
            font-weight: 700;
            width: 24%;
        }

        .signature-table td {
            height: 42px;
            text-align: center;
            vertical-align: bottom;
        }

        .signature-name {
            display: block;
            font-weight: 700;
            margin-bottom: 3px;
        }

        .signature-label {
            display: block;
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

        $hasAreaSelfie = fn ($log): bool => filled($log->area_selfie_path) || filled($log->area_selfie_image_data);
    @endphp

    @if ($letterheadDataUri)
        <img class="letterhead-page" src="{{ $letterheadDataUri }}" alt="">
        <div class="core-values">Excellence | Service | Leadership and Good Governance | Innovation | Social Responsibility | Integrity | Professionalism | Spirituality</div>
    @endif

    <div class="report-title">
        <h1>{{ $isSupervisor ? 'Patrol Logs Report' : 'My Patrol Logs Report' }}</h1>
        <p>RFID checkpoint scans, guard activity, checklist completion, and related incident references.</p>
    </div>

    <div class="section">
        <h2 class="section-title">Report Scope</h2>
        <table class="meta">
            <tr>
                <th>Generated</th>
                <td>{{ $generatedAt->format('M d, Y h:i A') }}</td>
                <th>Report Period</th>
                <td>{{ $reportPeriod }}</td>
            </tr>
            <tr>
                <th>Guard</th>
                <td>{{ $filters['guard'] }}</td>
                <th>Checkpoint</th>
                <td>{{ $filters['checkpoint'] }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>{{ $filters['status'] }}</td>
                <th>Date Filter</th>
                <td>{{ $filters['date'] }}</td>
            </tr>
            <tr>
                <th>Prepared For</th>
                <td>Security and Safety Office</td>
                <th>Records</th>
                <td>{{ $summary['total'] }} shown{{ $summary['total'] >= $recordLimit ? ' (latest '.$recordLimit.' records)' : '' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Summary</h2>
        <table class="summary">
            <tr>
                <th>Total Logs</th>
                <th>Valid</th>
                <th>Suspicious</th>
                <th>Invalid</th>
                <th>Pending Selfie</th>
                <th>Pending Checklist</th>
                <th>With Incident</th>
            </tr>
            <tr>
                <td>{{ $summary['total'] }}</td>
                <td>{{ $summary['valid'] }}</td>
                <td>{{ $summary['suspicious'] }}</td>
                <td>{{ $summary['invalid'] }}</td>
                <td>{{ $summary['pending_selfie'] }}</td>
                <td>{{ $summary['pending_checklist'] }}</td>
                <td>{{ $summary['incidents'] }}</td>
            </tr>
        </table>
    </div>

    <div class="records-section">
        <h2 class="section-title">Patrol Log Records</h2>
        <table>
            <thead>
                <tr>
                    <th class="w-no">No.</th>
                    <th class="w-time">Date / Time</th>
                    <th class="w-guard">Guard</th>
                    <th class="w-checkpoint">Checkpoint / Location</th>
                    <th class="w-rfid">RFID UID</th>
                    <th class="w-result">Status</th>
                    <th class="w-checklist">Checklist Result</th>
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
                        $checkpointName = $log->checkpoint?->name ?? 'Unknown';
                        $checkpointCode = $log->checkpoint?->code ?? $log->checkpoint_code ?? 'No code';
                    @endphp
                    <tr>
                        <td class="mono">{{ $loop->iteration }}</td>
                        <td>
                            <span class="strong">{{ $scanTime?->format('M d, Y') ?? 'Not recorded' }}</span>
                            <br>{{ $scanTime?->format('h:i A') ?? '' }}
                        </td>
                        <td>
                            <span class="strong">{{ $log->securityGuard?->name ?? 'Unknown' }}</span>
                            <br><span class="mono">{{ $log->securityGuard?->employee_no ?? 'No guard match' }}</span>
                        </td>
                        <td>
                            <span class="strong">{{ $checkpointName }}</span>
                            <br><span class="mono">{{ $checkpointCode }}</span>
                        </td>
                        <td><span class="mono">{{ $log->rfid_uid ?: 'Not recorded' }}</span></td>
                        <td>
                            <span class="strong">{{ $statusLabel($log->status) }}</span>
                            @if ($log->notes)
                                <br><span class="muted">{{ str($log->notes)->limit(55) }}</span>
                            @endif
                        </td>
                        <td>
                            <span class="strong">{{ $log->checklistSummary() }}</span>
                            @if ($checklist)
                                <br>
                                @if ($issueItems->isNotEmpty())
                                    <span class="muted">Issue: {{ $issueItems->implode(', ') }}</span>
                                @else
                                    <span class="muted">{{ $normalCount }} normal {{ str('item')->plural($normalCount) }}</span>
                                @endif
                                @if ($checklist->remarks)
                                    <br><span class="muted">Remarks: {{ str($checklist->remarks)->limit(50) }}</span>
                                @endif
                            @endif
                            <br><span class="muted">Area selfie: {{ $hasAreaSelfie($log) ? 'Captured' : 'Not captured' }}</span>
                        </td>
                        <td>
                            @if ($log->incidentReport)
                                <span class="strong">IR-{{ str_pad((string) $log->incidentReport->id, 6, '0', STR_PAD_LEFT) }}</span>
                                <br><span class="muted">{{ str($log->incidentReport->status)->replace('_', ' ')->title() }}</span>
                            @else
                                None
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8">No patrol logs found for this report scope.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Evidence and Checklist Policy</h2>
        <table class="policy">
            <tr>
                <td>Recommended Report Notes</td>
                <td>Patrol log PDFs should focus on attendance, checkpoint coverage, checklist completion, and incident references. Full checklist details remain available in the system when review is needed.</td>
            </tr>
            <tr>
                <td>Photo Handling</td>
                <td>Routine patrol proof is represented by the required area selfie. Incident photos belong in the incident report PDF because those images support a specific security event.</td>
            </tr>
            <tr>
                <td>Privacy</td>
                <td>Sensitive account data, passwords, raw diagnostics, and unrelated device details are not included in this report.</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Sign-Off</h2>
        <table class="signature-table">
            <tr>
                <th>Prepared By</th>
                <th>Reviewed By</th>
                <th>Date Reviewed</th>
            </tr>
            <tr>
                <td>
                    <span class="signature-name">{{ auth()->user()?->name ?? 'System User' }}</span>
                    <span class="signature-label">{{ $isSupervisor ? 'Supervisor' : 'Security Guard' }}</span>
                </td>
                <td>
                    <span class="signature-name">&nbsp;</span>
                    <span class="signature-label">Supervisor</span>
                </td>
                <td>
                    <span class="signature-name">{{ $generatedAt->format('M d, Y') }}</span>
                    <span class="signature-label">Security and Safety Office</span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
