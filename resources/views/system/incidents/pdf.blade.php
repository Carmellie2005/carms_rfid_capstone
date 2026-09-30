<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Incident Report {{ str_pad((string) $incident->id, 6, '0', STR_PAD_LEFT) }}</title>
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
            size: 612pt 936pt;
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
            height: 936pt;
            overflow: hidden;
            page-break-after: always;
            position: relative;
            width: 612pt;
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
            top: 126pt;
            width: 544pt;
            white-space: nowrap;
            z-index: 1;
        }

        .letterhead {
            height: 102pt;
            left: 75pt;
            position: absolute;
            top: 17pt;
            width: 300pt;
            z-index: 1;
        }

        .campus-meta {
            font-size: 7.2pt;
            left: 160pt;
            line-height: 1.15;
            position: absolute;
            top: 78pt;
            width: 250pt;
            z-index: 2;
        }

        .campus-meta a {
            color: #003f9f;
            text-decoration: underline;
        }

        .bagong {
            height: 76pt;
            left: 431pt;
            position: absolute;
            top: 19pt;
            width: 73pt;
            z-index: 1;
        }

        .header-rule {
            border-top: 1pt solid #111111;
            left: 52pt;
            position: absolute;
            top: 141pt;
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
            width: 612pt;
            z-index: 1;
        }

        .report-subtitle {
            font-size: 11pt;
            font-weight: 400;
            left: 0;
            position: absolute;
            text-align: center;
            top: 168pt;
            width: 612pt;
            z-index: 1;
        }

        .report-table,
        .details-table,
        .review-table,
        .signature-table {
            border-collapse: collapse;
            table-layout: fixed;
        }

        .report-table,
        .details-table,
        .review-table {
            left: 72.5pt;
            position: absolute;
            width: 467.21pt;
            z-index: 1;
        }

        .report-table {
            top: 196pt;
        }

        .details-table {
            top: 266pt;
        }

        .report-table td,
        .details-table td,
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
            height: 44pt;
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

        .incident-description-label,
        .documentation-label {
            font-size: 11pt;
            font-weight: 700;
            left: 72.5pt;
            position: absolute;
            width: 467.21pt;
            z-index: 1;
        }

        .incident-description-label {
            top: 496pt;
        }

        .description-body,
        .description-continuation {
            font-size: 11pt;
            left: 72.5pt;
            line-height: 1.45;
            position: absolute;
            text-align: justify;
            width: 467.21pt;
            z-index: 1;
        }

        .description-body {
            top: 522pt;
        }

        .description-continuation {
            top: 164pt;
        }

        .documentation-label {
            top: 164pt;
        }

        .documentation-photo {
            height: 337.5pt;
            left: 81pt;
            object-fit: cover;
            position: absolute;
            top: 197pt;
            width: 450pt;
            z-index: 1;
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
            height: 44pt;
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
            vertical-align: bottom;
            width: 50%;
        }

        .signature-name {
            border-top: 0.75pt solid #111111;
            display: block;
            font-weight: 700;
            margin-bottom: 3pt;
            padding-top: 3pt;
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
        $reportNumber = 'IR-'.str_pad((string) $incident->id, 6, '0', STR_PAD_LEFT);
        $priority = ucfirst($incident->priority ?? 'normal');
        $status = str($incident->status ?? 'submitted')->replace('_', ' ')->title();
        $category = $incident->category ?? 'Uncategorized';
        $incidentDate = $incident->incident_at?->timezone(config('app.timezone'))->format('M d, Y h:i A') ?? 'Not recorded';
        $reportedDate = $incident->reported_at?->timezone(config('app.timezone'))->format('M d, Y h:i A') ?? 'Not recorded';
        $resolvedDate = $incident->resolved_at ?: ($incident->status === 'resolved' ? $incident->updated_at : null);
        $resolvedDate = $resolvedDate?->timezone(config('app.timezone'));
        $patrol = $incident->patrolLog;
        $guardName = $incident->securityGuard?->name ?? 'Unknown';
        $employeeNo = $incident->securityGuard?->employee_no ?? 'Not recorded';
        $checkpointCode = $incident->checkpoint?->code ?? $patrol?->checkpoint_code ?? 'Not recorded';
        $location = $incident->checkpoint?->name ?? $incident->location ?? 'Unassigned';
        $locationCheckpoint = $checkpointCode !== 'Not recorded'
            ? $location.' - '.$checkpointCode
            : $location;
        $actionTaken = $incident->action_taken ?: 'No action recorded.';
        $resolvedDateLabel = $resolvedDate?->format('M d, Y h:i A') ?? 'Not yet resolved';
        $supervisorName = 'Ryan P. Tomol';
        $evidenceImages = collect($imageDataUris)->values();
        $narrative = $incident->description ?: 'No description provided.';
        $letterheadSrc = 'file:///'.str_replace('\\', '/', public_path('images/pdf-template/slsu-letterhead.png'));
        $bagongSrc = 'file:///'.str_replace('\\', '/', public_path('images/pdf-template/bagong-pilipinas.png'));
        $qsSrc = 'file:///'.str_replace('\\', '/', public_path('images/pdf-template/qs-rated-good.png'));
        $socotecSrc = 'file:///'.str_replace('\\', '/', public_path('images/pdf-template/socotec-iso9001.jpg'));
        $normalizeText = fn (string $value): string => trim(preg_replace("/\r\n|\r/", "\n", $value));
        $plainText = fn (string $value): string => trim(preg_replace('/\s+/u', ' ', $normalizeText($value)));
        $words = function (string $value) use ($plainText): array {
            $value = $plainText($value);

            return $value === ''
                ? []
                : preg_split('/\s+/u', $value, -1, PREG_SPLIT_NO_EMPTY);
        };
        $textLength = fn (string $value): int => function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        $textSlice = fn (string $value, int $start, ?int $length = null): string => function_exists('mb_substr')
            ? mb_substr($value, $start, $length)
            : substr($value, $start, $length);
        $limitWords = function (string $text, int $limit, string $fallback) use ($plainText, $words): string {
            $text = $plainText($text);

            if ($text === '') {
                return $fallback;
            }

            $wordList = $words($text);

            if (count($wordList) <= $limit) {
                return $text;
            }

            return implode(' ', array_slice($wordList, 0, $limit));
        };
        $splitText = function (string $text, int $firstLimit, int $continuationLimit, string $fallback) use ($normalizeText, $textLength, $textSlice): array {
            $chunks = [];
            $remaining = $normalizeText($text);

            while ($remaining !== '') {
                $limit = $chunks === [] ? $firstLimit : $continuationLimit;

                if ($textLength($remaining) <= $limit) {
                    $chunks[] = $remaining;
                    break;
                }

                $slice = $textSlice($remaining, 0, $limit);
                $breakAt = $textLength($slice);

                if (preg_match('/^(.{1,'.$limit.'})(?:\s+|$)/us', $remaining, $matches)) {
                    $breakAt = max(1, $textLength($matches[1]));
                }

                $chunk = trim($textSlice($remaining, 0, $breakAt));
                $remaining = trim($textSlice($remaining, $breakAt));

                if ($chunk === '') {
                    $chunk = trim($textSlice($remaining, 0, $limit));
                    $remaining = trim($textSlice($remaining, $limit));
                }

                $chunks[] = $chunk;
            }

            return $chunks !== [] ? $chunks : [$fallback];
        };
        $descriptionChunks = $splitText($narrative, 1200, 2400, 'No description provided.');
        $firstDescription = array_shift($descriptionChunks) ?: 'No description provided.';
        $descriptionContinuationChunks = collect($descriptionChunks);
        $actionTakenSummary = $limitWords($actionTaken, 30, 'No action recorded.');
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

        <div class="report-title">Security Incident Report</div>
        <div class="report-subtitle">Incident Documentation for Checkpoint Patrol Monitoring</div>

        <table class="report-table">
            <tr>
                <td>
                    <span class="label">Report No.</span>
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
                    <span class="label">Category:</span>
                    <span class="value">{{ $category }}</span>
                </td>
                <td>
                    <span class="label">Priority:</span>
                    <span class="value">{{ $priority }}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Incident Date / Time:</span>
                    <span class="value">{{ $incidentDate }}</span>
                </td>
                <td>
                    <span class="label">Reported Date / Time:</span>
                    <span class="value">{{ $reportedDate }}</span>
                </td>
            </tr>
        </table>

        <div class="incident-description-label">Incident Description:</div>
        <div class="description-body">{!! nl2br(e($firstDescription)) !!}</div>
    </section>

    @foreach ($descriptionContinuationChunks as $descriptionContinuation)
        <section class="page">
            {!! $pageChrome() !!}
            <div class="description-continuation">{!! nl2br(e($descriptionContinuation)) !!}</div>
        </section>
    @endforeach

    @forelse ($evidenceImages as $imageDataUri)
        <section class="page">
            {!! $pageChrome() !!}
            <div class="documentation-label">Documentation</div>
            <img class="documentation-photo" src="{{ $imageDataUri }}" alt="Incident documentation image {{ $loop->iteration }}">
        </section>
    @empty
        <section class="page">
            {!! $pageChrome() !!}
            <div class="documentation-label">Documentation</div>
            <div class="documentation-empty">No image evidence attached.</div>
        </section>
    @endforelse

    <section class="page">
        {!! $pageChrome() !!}

        <table class="review-table">
            <tr>
                <td>
                    <span class="label">Action Taken:</span>
                    <span class="value">{!! nl2br(e($actionTakenSummary)) !!}</span>
                </td>
            </tr>
            <tr>
                <td>
                    <span class="label">Resolved Date / Time:</span>
                    <span class="value">{{ $resolvedDateLabel }}</span>
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
