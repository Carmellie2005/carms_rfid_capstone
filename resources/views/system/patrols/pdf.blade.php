<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Patrol Logs Report</title>
    <style>
        @page {
            margin: {{ $letterheadDataUri ? '170px 42px 68px' : '42px' }};
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 7.7pt;
            line-height: 1.3;
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

        .title-block {
            border-bottom: 1px solid #111827;
            margin-bottom: 12px;
            padding-bottom: 7px;
            text-align: center;
        }

        h1 {
            font-size: 12pt;
            margin: 0;
            text-transform: uppercase;
        }

        .subtitle {
            font-size: 8.5pt;
            margin-top: 3px;
        }

        .section {
            margin-top: 12px;
            page-break-inside: avoid;
        }

        .section-title {
            border-bottom: 1px solid #111827;
            font-size: 8.8pt;
            font-weight: 700;
            margin: 0 0 6px;
            padding-bottom: 3px;
            text-transform: uppercase;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #111827;
            padding: 4px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        th {
            background: #dbeafe;
            font-size: 6.8pt;
            font-weight: 700;
            text-transform: uppercase;
        }

        .meta th {
            width: 20%;
        }

        .summary td {
            font-size: 10pt;
            font-weight: 700;
            text-align: center;
        }

        .w-time {
            width: 13%;
        }

        .w-guard {
            width: 16%;
        }

        .w-checkpoint {
            width: 16%;
        }

        .w-rfid {
            width: 12%;
        }

        .w-status {
            width: 13%;
        }

        .w-checklist {
            width: 16%;
        }

        .w-incident {
            width: 14%;
        }

        .muted {
            color: #4b5563;
        }

        .mono {
            font-family: DejaVu Sans Mono, monospace;
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

        .signature-row {
            margin-top: 34px;
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
            padding-top: 5px;
        }

        .clear {
            clear: both;
        }
    </style>
</head>
<body>
    @if ($letterheadDataUri)
        <img class="letterhead-page" src="{{ $letterheadDataUri }}" alt="">
    @endif

    <div class="footer-note">
        SLSU Bontoc Patrol - Patrol Logs Report - Generated {{ $generatedAt->format('M d, Y h:i A') }}
    </div>

    <div class="title-block">
        <h1>Patrol Logs Report</h1>
        <div class="subtitle">RFID checkpoint scans, face verification results, checklist status, and incident records</div>
    </div>

    <div class="section">
        <h2 class="section-title">Report Scope</h2>
        <table class="meta">
            <tr>
                <th>Generated</th>
                <td>{{ $generatedAt->format('M d, Y h:i A') }}</td>
                <th>Guard</th>
                <td>{{ $filters['guard'] }}</td>
            </tr>
            <tr>
                <th>Status</th>
                <td>{{ $filters['status'] }}</td>
                <th>Date</th>
                <td>{{ $filters['date'] }}</td>
            </tr>
            <tr>
                <th>Checkpoint</th>
                <td colspan="3">{{ $filters['checkpoint'] }}</td>
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
                <th>Pending Face</th>
                <th>Incidents</th>
            </tr>
            <tr>
                <td>{{ $summary['total'] }}</td>
                <td>{{ $summary['valid'] }}</td>
                <td>{{ $summary['suspicious'] }}</td>
                <td>{{ $summary['invalid'] }}</td>
                <td>{{ $summary['pending_face'] }}</td>
                <td>{{ $summary['incidents'] }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Log Records</h2>
        <table>
            <thead>
                <tr>
                    <th class="w-time">Date / Time</th>
                    <th class="w-guard">Guard</th>
                    <th class="w-checkpoint">Checkpoint</th>
                    <th class="w-rfid">RFID / Face</th>
                    <th class="w-status">Status</th>
                    <th class="w-checklist">Checklist</th>
                    <th class="w-incident">Incident</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $checklist = $log->checklistResponse;
                        $checkedItems = \App\Support\PatrolChecklist::checkedLabels($checklist)->implode(', ');
                    @endphp
                    <tr>
                        <td>{{ $log->scanned_at?->timezone(config('app.timezone'))->format('M d, Y h:i A') ?? 'Not recorded' }}</td>
                        <td>
                            {{ $log->securityGuard?->name ?? 'Unknown' }}
                            <br><span class="muted">{{ $log->securityGuard?->employee_no ?? 'No guard match' }}</span>
                        </td>
                        <td>
                            {{ $log->checkpoint?->name ?? 'Unknown' }}
                            <br><span class="muted mono">{{ $log->checkpoint?->code ?? $log->checkpoint_code ?? 'No code' }}</span>
                        </td>
                        <td>
                            <span class="mono">{{ $log->rfid_uid }}</span>
                            <br>{{ str($log->facial_status)->replace('_', ' ')->title() }}
                        </td>
                        <td>
                            {{ str($log->status)->replace('_', ' ')->title() }}
                            @if ($log->notes)
                                <br><span class="muted">{{ str($log->notes)->limit(90) }}</span>
                            @endif
                        </td>
                        <td>
                            {{ $checkedItems ?: 'No checklist items' }}
                            @if ($checklist?->remarks)
                                <br><span class="muted">{{ str($checklist->remarks)->limit(80) }}</span>
                            @endif
                        </td>
                        <td>
                            @if ($log->incidentReport)
                                {{ $log->incidentReport->category }}
                                <br><span class="muted">{{ str($log->incidentReport->status)->replace('_', ' ')->title() }}</span>
                            @else
                                None
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="muted">No patrol logs found for this report scope.</td>
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
