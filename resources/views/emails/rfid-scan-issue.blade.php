@php
    $patrol = $patrolLog;
    $guard = $patrol->securityGuard;
    $checkpoint = $patrol->checkpoint;
    $scanTime = $patrol->scanned_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? 'Not recorded';
@endphp

<h1>RFID Scan Issue</h1>

<p>An ESP32 RFID reader recorded a scan that needs supervisor review.</p>

<ul>
    <li><strong>Status:</strong> {{ str($patrol->status)->replace('_', ' ')->title() }}</li>
    <li><strong>RFID UID:</strong> {{ $patrol->rfid_uid ?: 'Not recorded' }}</li>
    <li><strong>Guard:</strong> {{ $guard?->name ?? 'Unknown or inactive guard' }}{{ $guard?->employee_no ? ' ('.$guard->employee_no.')' : '' }}</li>
    <li><strong>Checkpoint:</strong> {{ $checkpoint?->name ?? $patrol->checkpoint_code ?? 'Unknown checkpoint' }}</li>
    <li><strong>Scanned At:</strong> {{ $scanTime }}</li>
</ul>

<p><strong>Details:</strong></p>
<p>{{ $patrol->notes ?: 'No additional details were recorded.' }}</p>

<p>
    <a href="{{ route('scan-issues.index') }}">Open scan issues</a>
</p>
