@php
    $incident = $incidentReport;
    $guard = $incident->securityGuard;
    $checkpoint = $incident->checkpoint;
    $incidentTime = $incident->incident_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? 'Not recorded';
@endphp

<h1>Incident Report Submitted</h1>

<p>A guard submitted an incident report in SLSU BC Patrol.</p>

<ul>
    <li><strong>Category:</strong> {{ $incident->category ?: 'Incident report' }}</li>
    <li><strong>Priority:</strong> {{ ucfirst($incident->priority ?: 'normal') }}</li>
    <li><strong>Guard:</strong> {{ $guard?->name ?? 'Unknown guard' }}{{ $guard?->employee_no ? ' ('.$guard->employee_no.')' : '' }}</li>
    <li><strong>Checkpoint:</strong> {{ $checkpoint?->name ?? 'Unassigned checkpoint' }}</li>
    <li><strong>Location:</strong> {{ $incident->location ?: $checkpoint?->location ?: 'Not recorded' }}</li>
    <li><strong>Reported At:</strong> {{ $incidentTime }}</li>
</ul>

<p><strong>Description:</strong></p>
<p>{{ $incident->description }}</p>

<p>
    <a href="{{ route('incidents.index', ['status' => $incident->status]) }}">Open incident reports</a>
</p>
