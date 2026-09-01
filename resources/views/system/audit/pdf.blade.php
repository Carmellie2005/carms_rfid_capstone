<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Audit Trail Report</title>
    <style>
        @page {
            margin: {{ $letterheadDataUri ? '170px 42px 60px' : '42px' }};
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #111827;
            font-family: DejaVu Sans, sans-serif;
            font-size: 8.5pt;
            line-height: 1.35;
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
            margin-bottom: 14px;
            padding-bottom: 8px;
            text-align: center;
        }

        h1 {
            font-size: 13pt;
            margin: 0;
            text-transform: uppercase;
        }

        .subtitle {
            font-size: 9pt;
            margin-top: 3px;
        }

        .section {
            margin-top: 14px;
            page-break-inside: avoid;
        }

        .section-title {
            border-bottom: 1px solid #111827;
            font-size: 9.5pt;
            font-weight: 700;
            margin: 0 0 7px;
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
            padding: 5px;
            text-align: left;
            vertical-align: top;
            word-wrap: break-word;
        }

        th {
            background: #dbeafe;
            font-size: 7.5pt;
            font-weight: 700;
            text-transform: uppercase;
        }

        .meta th {
            width: 24%;
        }

        .summary td {
            font-size: 12pt;
            font-weight: 700;
            text-align: center;
        }

        .w-time {
            width: 15%;
        }

        .w-actor {
            width: 15%;
        }

        .w-action {
            width: 15%;
        }

        .w-description {
            width: 27%;
        }

        .w-ip {
            width: 11%;
        }

        .w-details {
            width: 17%;
        }

        .details {
            font-family: DejaVu Sans Mono, monospace;
            font-size: 6.8pt;
            white-space: pre-wrap;
        }

        .muted {
            color: #4b5563;
        }

        .signature-row {
            margin-top: 38px;
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

    <div class="title-block">
        <h1>Audit Trail Report</h1>
        <div class="subtitle">SLSU Bontoc Patrol system activity documentation</div>
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
                <th>Action</th>
                <td>{{ $filters['action'] }}</td>
                <th>Date</th>
                <td>{{ $filters['date'] }}</td>
            </tr>
            <tr>
                <th>Search</th>
                <td colspan="3">{{ $filters['search'] }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Summary</h2>
        <table class="summary">
            <tr>
                <th>Total audit records</th>
                <td>{{ $summary['total'] }}</td>
                <th>Report type</th>
                <td>{{ $selectedGuard ? 'Specific guard' : 'All guards' }}</td>
            </tr>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Actions Included</h2>
        <table>
            <thead>
                <tr>
                    <th>Action</th>
                    <th>Records</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($summary['actions'] as $action)
                    <tr>
                        <td>{{ $action['action'] }}</td>
                        <td>{{ $action['count'] }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="2" class="muted">No audit actions found for this report scope.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2 class="section-title">Audit Records</h2>
        <table>
            <thead>
                <tr>
                    <th class="w-time">Time</th>
                    <th class="w-actor">Actor</th>
                    <th class="w-action">Action</th>
                    <th class="w-description">Description</th>
                    <th class="w-ip">IP</th>
                    <th class="w-details">Details</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($logs as $log)
                    @php
                        $details = $log->properties
                            ? json_encode($log->properties, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                            : 'None';
                    @endphp
                    <tr>
                        <td>{{ $log->created_at->timezone(config('app.timezone'))->format('M d, Y h:i A') }}</td>
                        <td>
                            {{ $log->actor_name ?: 'System' }}
                            @if ($log->user?->email)
                                <br><span class="muted">{{ $log->user->email }}</span>
                            @endif
                        </td>
                        <td>{{ str($log->action)->replace('_', ' ')->title() }}</td>
                        <td>{{ $log->description }}</td>
                        <td>{{ $log->ip_address ?: 'N/A' }}</td>
                        <td class="details">{{ str($details)->limit(360) }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="muted">No audit records found for this report scope.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="signature-row">
        <div class="signature">
            <div class="signature-line">{{ auth()->user()?->name ?? 'System Supervisor' }}</div>
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
