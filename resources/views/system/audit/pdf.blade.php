<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Audit Trail Report</title>
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
            font-size: 10.5pt;
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
            height: 85pt;
            left: 140pt;
            position: absolute;
            top: 30pt;
            width: 250pt;
            z-index: 1;
        }

        .campus-meta {
            font-family: "Poppins", "Calibri", "DejaVu Sans", sans-serif;
            font-size: 6.3pt;
            left: 208pt;
            line-height: 1.15;
            position: absolute;
            top: 75pt;
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
            top: 153pt;
            width: 595.28pt;
            z-index: 1;
        }

        .report-subtitle {
            font-size: 11pt;
            font-weight: 400;
            left: 0;
            position: absolute;
            text-align: center;
            top: 168pt;
            width: 595.28pt;
            z-index: 1;
        }

        .report-table,
        .scope-table,
        .audit-table {
            border-collapse: collapse;
            left: 72.5pt;
            position: absolute;
            table-layout: fixed;
            width: 467.21pt;
            z-index: 1;
        }

        .report-table {
            top: 193pt;
        }

        .scope-table {
            top: 258pt;
        }

        .audit-table-first {
            top: 378pt;
        }

        .audit-table-following {
            top: 188pt;
        }

        .report-table td,
        .scope-table td,
        .audit-table th,
        .audit-table td {
            border: 0.75pt solid #111111;
            vertical-align: top;
            word-wrap: break-word;
        }

        .report-table td {
            font-size: 9.8pt;
            height: 48pt;
            line-height: 1.12;
            padding: 4pt 6pt;
            width: 33.333%;
        }

        .scope-table td {
            font-size: 9.2pt;
            height: 27pt;
            line-height: 1.08;
            padding: 3pt 5pt;
            width: 50%;
        }

        .audit-table th {
            background: #f1f5f9;
            font-size: 6.6pt;
            font-weight: 700;
            line-height: 1;
            padding: 3pt;
            text-align: left;
            text-transform: uppercase;
        }

        .audit-table td {
            font-size: 6.8pt;
            line-height: 1.03;
            padding: 3pt;
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

        .section-label {
            font-size: 11pt;
            font-weight: 700;
            left: 72.5pt;
            position: absolute;
            width: 467.21pt;
            z-index: 1;
        }

        .scope-label {
            top: 240pt;
        }

        .audit-label-first {
            top: 356pt;
        }

        .audit-label-following {
            top: 166pt;
        }

        .w-time {
            width: 64pt;
        }

        .w-actor {
            width: 62pt;
        }

        .w-action {
            width: 62pt;
        }

        .w-diagnostic {
            width: 164pt;
        }

        .w-window {
            width: 70pt;
        }

        .w-result {
            width: 45.21pt;
        }

        .muted {
            color: #444444;
        }

        .empty-row td {
            font-size: 10pt;
            padding: 12pt 6pt;
            text-align: center;
        }
    </style>
</head>
<body>
    @php
        $timezone = config('app.timezone');
        $generatedBy = auth()->user()?->name ?? 'System Supervisor';
        $reportNumber = 'AT-'.$generatedAt->format('YmdHis');
        $logsForPdf = collect($logs)->values();
        $firstPageLogs = $logsForPdf->take(6);
        $followingPageLogs = $logsForPdf->slice(6)->values()->chunk(9);
        $actionTypeCount = collect($summary['actions'])->count();
        $recordLabel = $summary['total'] === 1 ? 'record' : 'records';
        $letterheadSrc = 'file:///'.str_replace('\\', '/', public_path('images/pdf-template/slsu-letterhead.png'));
        $bagongSrc = 'file:///'.str_replace('\\', '/', public_path('images/pdf-template/bagong-pilipinas.png'));
        $qsSrc = 'file:///'.str_replace('\\', '/', public_path('images/pdf-template/qs-rated-good.png'));
        $socotecSrc = 'file:///'.str_replace('\\', '/', public_path('images/pdf-template/socotec-iso9001.jpg'));
        $formatDate = fn ($date) => $date
            ? $date->timezone($timezone)->format('M d, Y h:i A')
            : 'Not recorded';
        $limitText = fn ($value, $limit = 92) => \Illuminate\Support\Str::limit((string) $value, $limit);
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

        <div class="report-title">Audit Trail Report</div>
        <div class="report-subtitle">Filtered System Activity Documentation</div>

        <table class="report-table">
            <tr>
                <td>
                    <span class="label">Audit Report No.</span>
                    <span class="value">{{ $reportNumber }}</span>
                </td>
                <td>
                    <span class="label">Total Records:</span>
                    <span class="value">{{ $summary['total'] }} {{ $recordLabel }}</span>
                </td>
                <td>
                    <span class="label">Generated:</span>
                    <span class="value">{{ $generatedAt->format('M d, Y h:i A') }}</span>
                </td>
            </tr>
        </table>

        <div class="section-label scope-label">Applied Filters:</div>
        <table class="scope-table">
            <tr>
                <td>
                    <span class="label">Guard:</span>
                    <span class="value">{{ $filters['guard'] }}</span>
                </td>
                <td>
                    <span class="label">Action:</span>
                    <span class="value">{{ $filters['action'] }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Date:</span>
                    <span class="value">{{ $filters['date'] }}</span>
                </td>
                <td>
                    <span class="label">Search:</span>
                    <span class="value">{{ $filters['search'] }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Generated By:</span>
                    <span class="value">{{ $generatedBy }}</span>
                </td>
                <td>
                    <span class="label">Action Types Included:</span>
                    <span class="value">{{ $actionTypeCount }}</span>
                </td>
            </tr>
        </table>

        <div class="section-label audit-label-first">Audit Records:</div>
        <table class="audit-table audit-table-first">
            <colgroup>
                <col class="w-time">
                <col class="w-actor">
                <col class="w-action">
                <col class="w-diagnostic">
                <col class="w-window">
                <col class="w-result">
            </colgroup>
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Actor</th>
                    <th>Action</th>
                    <th>Diagnostic</th>
                    <th>Window</th>
                    <th>Result</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($firstPageLogs as $log)
                    <tr>
                        <td>{{ $formatDate($log->created_at) }}</td>
                        <td>{{ $limitText($log->actor_name ?: 'System', 32) }}</td>
                        <td>{{ $limitText(str($log->action)->replace('_', ' ')->title(), 32) }}</td>
                        <td>{{ $limitText($log->diagnosticSummary(), 82) }}</td>
                        <td>{{ $limitText($log->patrolWindowSummary(), 36) }}</td>
                        <td>{{ $limitText($log->resultLabel(), 18) }}</td>
                    </tr>
                @empty
                    <tr class="empty-row">
                        <td colspan="6" class="muted">No audit records found for this report scope.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </section>

    @foreach ($followingPageLogs as $logChunk)
        <section class="page">
            {!! $pageChrome() !!}

            <div class="section-label audit-label-following">Audit Records Continued:</div>
            <table class="audit-table audit-table-following">
                <colgroup>
                    <col class="w-time">
                    <col class="w-actor">
                    <col class="w-action">
                    <col class="w-diagnostic">
                    <col class="w-window">
                    <col class="w-result">
                </colgroup>
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Actor</th>
                        <th>Action</th>
                        <th>Diagnostic</th>
                        <th>Window</th>
                        <th>Result</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logChunk as $log)
                        <tr>
                            <td>{{ $formatDate($log->created_at) }}</td>
                            <td>{{ $limitText($log->actor_name ?: 'System', 32) }}</td>
                            <td>{{ $limitText(str($log->action)->replace('_', ' ')->title(), 32) }}</td>
                            <td>{{ $limitText($log->diagnosticSummary(), 82) }}</td>
                            <td>{{ $limitText($log->patrolWindowSummary(), 36) }}</td>
                            <td>{{ $limitText($log->resultLabel(), 18) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endforeach
</body>
</html>
