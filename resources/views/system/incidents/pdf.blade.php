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

        .page-background {
            height: 936pt;
            left: 0;
            position: absolute;
            top: 0;
            width: 612pt;
            z-index: 0;
        }

        .html-title {
            font-size: 11pt;
            font-weight: 700;
            left: 0;
            letter-spacing: 0;
            position: absolute;
            text-align: center;
            top: 114.8pt;
            width: 612pt;
            z-index: 1;
        }

        .html-subtitle {
            font-size: 11pt;
            font-weight: 400;
            left: 0;
            position: absolute;
            text-align: center;
            top: 129.4pt;
            width: 612pt;
            z-index: 1;
        }

        .core-values {
            font-family: "Poppins", "Calibri", "DejaVu Sans", sans-serif;
            font-size: 6pt;
            font-weight: 400;
            left: 104.7pt;
            letter-spacing: -0.01em;
            line-height: 1;
            position: absolute;
            top: 87.3pt;
            white-space: nowrap;
            z-index: 1;
        }

        .field {
            overflow: hidden;
            padding: 4.5pt 5.2pt;
            position: absolute;
            z-index: 1;
        }

        .label {
            display: block;
            font-size: 11pt;
            font-weight: 700;
            letter-spacing: 0;
            line-height: 1.15;
            margin-bottom: 3pt;
            text-transform: uppercase;
        }

        .value {
            display: block;
            font-size: 11pt;
            font-weight: 400;
            line-height: 1.25;
        }

        .name-value {
            font-weight: 700;
        }

        .narrative-label,
        .evidence-label {
            font-size: 11pt;
            font-weight: 700;
            left: 71.3pt;
            position: absolute;
            z-index: 1;
        }

        .narrative-text {
            font-size: 11pt;
            line-height: 1.45;
            padding: 24pt 20pt;
            text-align: justify;
        }

        .continuation-title {
            font-size: 11pt;
            font-weight: 700;
            left: 0;
            position: absolute;
            text-align: center;
            top: 114.8pt;
            width: 612pt;
            z-index: 1;
        }

        .continuation-subtitle {
            font-size: 11pt;
            left: 0;
            position: absolute;
            text-align: center;
            top: 129.4pt;
            width: 612pt;
            z-index: 1;
        }

        .continuation-meta {
            font-size: 10pt;
            left: 72.5pt;
            position: absolute;
            top: 160pt;
            width: 467.21pt;
            z-index: 1;
        }

        .continuation-label {
            font-size: 11pt;
            font-weight: 700;
            left: 72.5pt;
            position: absolute;
            top: 188pt;
            z-index: 1;
        }

        .continuation-box {
            border: 0.75pt solid #4b5563;
            font-size: 11pt;
            height: 600pt;
            left: 72.5pt;
            line-height: 1.45;
            padding: 18pt 20pt;
            position: absolute;
            text-align: justify;
            top: 214pt;
            width: 467.21pt;
            z-index: 1;
        }

        .continuation-footer {
            bottom: 67pt;
            color: #4b5563;
            font-size: 9pt;
            left: 72.5pt;
            position: absolute;
            text-align: center;
            width: 467.21pt;
            z-index: 1;
        }

        .report-no {
            height: 58.56pt;
            left: 72.5pt;
            top: 160.37pt;
            width: 155.35pt;
        }

        .status {
            height: 58.56pt;
            left: 228.34pt;
            top: 160.37pt;
            width: 155.57pt;
        }

        .generated {
            height: 58.56pt;
            left: 384.38pt;
            top: 160.37pt;
            width: 155.33pt;
        }

        .category {
            height: 43.94pt;
            left: 72.5pt;
            top: 234.77pt;
            width: 233.38pt;
        }

        .priority {
            height: 43.94pt;
            left: 306.36pt;
            top: 234.77pt;
            width: 233.35pt;
        }

        .incident-date {
            height: 43.92pt;
            left: 72.5pt;
            top: 279.19pt;
            width: 233.38pt;
        }

        .reported-date {
            height: 43.92pt;
            left: 306.36pt;
            top: 279.19pt;
            width: 233.35pt;
        }

        .location {
            height: 43.94pt;
            left: 72.5pt;
            top: 323.6pt;
            width: 467.21pt;
        }

        .guard {
            height: 43.92pt;
            left: 72.5pt;
            top: 383.38pt;
            width: 233.38pt;
        }

        .employee {
            height: 43.92pt;
            left: 306.36pt;
            top: 383.38pt;
            width: 233.35pt;
        }

        .narrative-label {
            top: 431.6pt;
        }

        .narrative-box {
            height: 249.17pt;
            left: 72.5pt;
            top: 457.56pt;
            width: 467.21pt;
        }

        .evidence-label {
            top: 105.1pt;
        }

        .evidence-box {
            height: 234.55pt;
            left: 72.5pt;
            padding: 26pt 22pt;
            top: 131.07pt;
            width: 467.21pt;
        }

        .photo-grid {
            border-collapse: collapse;
            width: 100%;
        }

        .photo-grid td {
            height: 86pt;
            padding: 8pt 7pt;
            text-align: center;
            vertical-align: middle;
            width: 50%;
        }

        .photo-grid img {
            max-height: 74pt;
            max-width: 100%;
        }

        .empty-evidence {
            font-size: 11pt;
            padding-top: 74pt;
            text-align: center;
        }

        .review-flow {
            background: #ffffff;
            left: 72.5pt;
            position: absolute;
            top: 410.5pt;
            width: 467.21pt;
            z-index: 2;
        }

        .review-flow-field {
            border: 0.75pt solid #555555;
            border-bottom: 0;
            padding: 5pt 7pt 6pt;
        }

        .review-flow-field:last-of-type {
            border-bottom: 0.75pt solid #555555;
        }

        .review-flow-normal .review-flow-field {
            min-height: 43.92pt;
        }

        .review-flow .label {
            margin-bottom: 3pt;
        }

        .review-flow .value {
            font-size: 10.5pt;
            line-height: 1.2;
            text-align: justify;
        }

        .review-flow .resolved-field .value {
            text-align: left;
        }

        .review-flow-compact .review-flow-field {
            padding: 4pt 6pt 4.5pt;
        }

        .review-flow-compact .label {
            font-size: 9.5pt;
            margin-bottom: 2pt;
        }

        .review-flow-compact .value {
            font-size: 8.8pt;
            line-height: 1.12;
        }

        .review-flow-dense .review-flow-field {
            padding: 3pt 5pt 3.5pt;
        }

        .review-flow-dense .label {
            font-size: 8.2pt;
            margin-bottom: 1.2pt;
        }

        .review-flow-dense .value {
            font-size: 6.8pt;
            line-height: 1.05;
        }

        .review-signatures {
            border-collapse: collapse;
            margin-top: 18pt;
            table-layout: fixed;
            width: 100%;
        }

        .review-flow-compact .review-signatures {
            margin-top: 12pt;
        }

        .review-flow-dense .review-signatures {
            margin-top: 8pt;
        }

        .review-signatures td {
            border: 0;
            font-size: 11pt;
            line-height: 1.15;
            padding: 0 8pt;
            text-align: center;
            vertical-align: bottom;
            width: 33.333%;
        }

        .review-flow-compact .review-signatures td {
            font-size: 9.5pt;
        }

        .review-flow-dense .review-signatures td {
            font-size: 7.8pt;
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
        $reviewNotes = $incident->admin_notes ?: 'No supervisor review notes recorded.';
        $actionTaken = $incident->action_taken ?: 'No action recorded.';
        $resolvedDateLabel = $resolvedDate?->format('M d, Y h:i A') ?? 'Not yet resolved';
        $reviewedDateLabel = $resolvedDate?->format('M d, Y') ?? $generatedAt->format('M d, Y');
        $supervisorName = 'Ryan P. Tomol';
        $evidenceImages = collect($imageDataUris)->take(4)->values();
        $narrative = $incident->description ?: 'No description provided.';
        $normalizeText = fn (string $value): string => trim(preg_replace("/\r\n|\r/", "\n", $value));
        $textLength = fn (string $value): int => function_exists('mb_strlen') ? mb_strlen($value) : strlen($value);
        $textSlice = fn (string $value, int $start, ?int $length = null): string => function_exists('mb_substr')
            ? mb_substr($value, $start, $length)
            : substr($value, $start, $length);
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
        $narrativeChunks = collect($splitText($narrative, 950, 2200, 'No description provided.'));
        $firstNarrativeChunk = $narrativeChunks->first();
        $continuationNarrativeChunks = $narrativeChunks->slice(1)->values();
        $reviewFlowLength = $textLength($normalizeText($reviewNotes).' '.$normalizeText($actionTaken));
        $reviewFlowDensity = $reviewFlowLength > 1800
            ? 'dense'
            : ($reviewFlowLength > 700 ? 'compact' : 'normal');
    @endphp

    <section class="page">
        @if ($incidentFormPageOneDataUri)
            <img class="page-background" src="{{ $incidentFormPageOneDataUri }}" alt="">
        @endif

        <div class="core-values">Excellence | Service | Leadership and Good Governance | Innovation | Social Responsibility | Integrity | Professionalism | Spirituality</div>
        <div class="html-title">Security Incident Report</div>
        <div class="html-subtitle">Incident Documentation for Checkpoint Patrol Monitoring</div>

        <div class="field report-no">
            <span class="label">Report No.</span>
            <span class="value">{{ $reportNumber }}</span>
        </div>
        <div class="field status">
            <span class="label">Status:</span>
            <span class="value">{{ $status }}</span>
        </div>
        <div class="field generated">
            <span class="label">Generated:</span>
            <span class="value">{{ $generatedAt->format('M d, Y h:i A') }}</span>
        </div>
        <div class="field category">
            <span class="label">Category:</span>
            <span class="value">{{ $category }}</span>
        </div>
        <div class="field priority">
            <span class="label">Priority:</span>
            <span class="value">{{ $priority }}</span>
        </div>
        <div class="field incident-date">
            <span class="label">Incident Date / Time:</span>
            <span class="value">{{ $incidentDate }}</span>
        </div>
        <div class="field reported-date">
            <span class="label">Reported Date / Time:</span>
            <span class="value">{{ $reportedDate }}</span>
        </div>
        <div class="field location">
            <span class="label">Location / Checkpoint:</span>
            <span class="value">{{ $locationCheckpoint }}</span>
        </div>
        <div class="field guard">
            <span class="label">Security Guard:</span>
            <span class="value name-value">{{ $guardName }}</span>
        </div>
        <div class="field employee">
            <span class="label">Employee No.</span>
            <span class="value">{{ $employeeNo }}</span>
        </div>

        <div class="narrative-label">Narrative of Incident:</div>
        <div class="field narrative-box narrative-text">{!! nl2br(e($firstNarrativeChunk)) !!}</div>
    </section>

    @foreach ($continuationNarrativeChunks as $continuationIndex => $continuationNarrative)
        <section class="page">
            <div class="core-values">Excellence | Service | Leadership and Good Governance | Innovation | Social Responsibility | Integrity | Professionalism | Spirituality</div>
            <div class="continuation-title">Security Incident Report</div>
            <div class="continuation-subtitle">Narrative of Incident Continuation</div>
            <div class="continuation-meta">Report No.: {{ $reportNumber }} &nbsp; | &nbsp; Security Guard: {{ $guardName }}</div>
            <div class="continuation-label">Narrative of Incident (continued):</div>
            <div class="continuation-box">{!! nl2br(e($continuationNarrative)) !!}</div>
            <div class="continuation-footer">Narrative continuation page {{ $continuationIndex + 1 }} of {{ $continuationNarrativeChunks->count() }}</div>
        </section>
    @endforeach

    <section class="page">
        @if ($incidentFormPageTwoDataUri)
            <img class="page-background" src="{{ $incidentFormPageTwoDataUri }}" alt="">
        @endif

        <div class="core-values">Excellence | Service | Leadership and Good Governance | Innovation | Social Responsibility | Integrity | Professionalism | Spirituality</div>
        <div class="evidence-label">Evidence (Photo):</div>

        <div class="field evidence-box">
            @if ($evidenceImages->isNotEmpty())
                <table class="photo-grid">
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

        <div class="review-flow review-flow-{{ $reviewFlowDensity }}">
            <div class="review-flow-field">
                <span class="label">Review Notes:</span>
                <span class="value">{!! nl2br(e($reviewNotes)) !!}</span>
            </div>
            <div class="review-flow-field">
                <span class="label">Action Taken:</span>
                <span class="value">{!! nl2br(e($actionTaken)) !!}</span>
            </div>
            <div class="review-flow-field resolved-field">
                <span class="label">Resolved Date / Time:</span>
                <span class="value">{{ $resolvedDateLabel }}</span>
            </div>

            <table class="review-signatures">
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
                    <td>
                        <span class="signature-name">{{ $reviewedDateLabel }}</span>
                        <span class="signature-label">Date Reviewed</span>
                    </td>
                </tr>
            </table>
        </div>
    </section>
</body>
</html>
