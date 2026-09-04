<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">
                    Welcome, {{ auth()->user()->name }}
                </h2>
                <p class="mt-1 text-sm text-blue-600">RFID patrol monitoring, verification, and incident reporting</p>
            </div>
        </div>
    </x-slot>

    @php
        $statusClasses = [
            'valid' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'suspicious' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'invalid' => 'bg-red-50 text-red-700 ring-red-200',
            'pending_face' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'profile_incomplete' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'outside_schedule' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'expired' => 'bg-slate-50 text-slate-700 ring-slate-200',
            'pending' => 'bg-slate-50 text-slate-700 ring-slate-200',
        ];

        $priorityClasses = [
            'low' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'normal' => 'bg-slate-50 text-slate-700 ring-slate-200',
            'high' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'critical' => 'bg-red-50 text-red-700 ring-red-200',
        ];

        $incidentStatusClasses = [
            'submitted' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'under_review' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'resolved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        ];

        $summaryCards = [
            ['label' => 'Active Guards', 'value' => $stats['activeGuards'], 'cardClass' => 'border-emerald-100 bg-emerald-50/60', 'labelClass' => 'text-emerald-700', 'valueClass' => 'text-emerald-900'],
            ['label' => 'Checkpoints', 'value' => $stats['activeCheckpoints'], 'cardClass' => 'border-sky-100 bg-sky-50/60', 'labelClass' => 'text-sky-700', 'valueClass' => 'text-sky-900'],
            ['label' => 'Today Patrols', 'value' => $stats['todayPatrols'], 'cardClass' => 'border-blue-100 bg-white', 'labelClass' => 'text-blue-700', 'valueClass' => 'text-blue-950'],
            ['label' => 'Open Incidents', 'value' => $stats['openIncidents'], 'cardClass' => 'border-amber-100 bg-amber-50/60', 'labelClass' => 'text-amber-700', 'valueClass' => 'text-amber-900'],
            ['label' => 'For Review', 'value' => $stats['suspiciousScans'], 'cardClass' => 'border-red-100 bg-red-50/60', 'labelClass' => 'text-red-700', 'valueClass' => 'text-red-900'],
        ];

        $scanStatusTotal = collect($analytics['scanStatus']['data'])->sum();
        $scanStatusLegend = [
            ['label' => 'Valid', 'color' => 'bg-emerald-500'],
            ['label' => 'Suspicious', 'color' => 'bg-amber-500'],
            ['label' => 'Invalid', 'color' => 'bg-red-500'],
            ['label' => 'Profile Incomplete', 'color' => 'bg-violet-500'],
            ['label' => 'Outside Schedule', 'color' => 'bg-amber-500'],
        ];
    @endphp

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800">
                    {{ session('status') }}
                </div>
            @endif

            <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                @foreach ($summaryCards as $card)
                    <div class="min-h-[6.25rem] rounded-md border p-3 shadow-sm sm:p-5 {{ $card['cardClass'] }}">
                        <p class="truncate whitespace-nowrap text-[0.7rem] font-semibold uppercase tracking-wide sm:text-xs {{ $card['labelClass'] }}">{{ $card['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold sm:mt-3 sm:text-3xl {{ $card['valueClass'] }}">{{ $card['value'] }}</p>
                    </div>
                @endforeach
            </section>

            <section
                x-data="dashboardCharts(@js($analytics))"
                x-init="render()"
                x-on:theme-changed.window="render()"
                x-on:resize.window.debounce.300ms="render()"
                class="grid gap-4 xl:grid-cols-2"
            >
                <div class="rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-blue-950">Patrol Trend</h3>
                            <p class="text-sm text-slate-500">Total checkpoint scans in the last 7 days</p>
                        </div>
                    </div>
                    <div class="mt-3 h-48 min-w-0 sm:h-56 lg:h-60">
                        <canvas x-ref="patrolTrendChart" class="block h-full w-full" aria-label="Patrol trend chart"></canvas>
                    </div>
                </div>

                <div class="grid gap-4 lg:grid-cols-2">
                    <div class="rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                        <h3 class="font-semibold text-blue-950">Scan Status</h3>
                        <p class="mt-1 text-sm leading-5 text-slate-500">Valid, suspicious, invalid, profile incomplete, and outside schedule scans</p>
                        <div class="mt-3 grid grid-cols-2 items-center gap-x-4 gap-y-1.5 text-xs text-slate-600 sm:text-sm">
                            @foreach ($scanStatusLegend as $item)
                                <div class="grid min-w-0 grid-cols-[0.75rem_minmax(0,1fr)] items-center gap-2">
                                    <span class="h-3 w-3 rounded-full {{ $item['color'] }}" aria-hidden="true"></span>
                                    <span class="truncate whitespace-nowrap leading-5">{{ $item['label'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <div class="relative mt-3 h-40 min-w-0 sm:h-44 lg:h-48">
                            <canvas x-ref="scanStatusChart" class="block h-full w-full" aria-label="Scan status chart"></canvas>
                            @if ($scanStatusTotal === 0)
                                <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm font-medium text-slate-400">
                                    No scan data yet
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                        <h3 class="font-semibold text-blue-950">Incident Priority</h3>
                        <p class="text-sm text-slate-500">Submitted incidents by priority</p>
                        <div class="relative mt-3 h-40 min-w-0 sm:h-48 lg:h-52">
                            <canvas x-ref="incidentPriorityChart" class="block h-full w-full" aria-label="Incident priority chart"></canvas>
                            @if (collect($analytics['incidentPriority']['data'])->sum() === 0)
                                <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm font-medium text-slate-400">
                                    No incident data yet
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-5 xl:col-span-2">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-blue-950">Checkpoint Activity Analytics</h3>
                            <p class="text-sm text-slate-500">Valid patrols recorded per active checkpoint</p>
                        </div>
                        <a href="{{ route('checkpoints.index') }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">Manage checkpoints</a>
                    </div>
                    <div class="relative mt-3 h-52 min-w-0 sm:h-56 lg:h-60">
                        <canvas x-ref="checkpointActivityChart" class="block h-full w-full" aria-label="Checkpoint activity chart"></canvas>
                        @if (collect($analytics['checkpointActivity']['data'])->sum() === 0)
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm font-medium text-slate-400">
                                No checkpoint activity yet
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1fr_360px]">
                <div class="overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-blue-100 px-5 py-4">
                        <h3 class="font-semibold text-blue-950">Recent Patrol Logs</h3>
                        <a href="{{ route('patrol-logs.index') }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">View all</a>
                    </div>

                    <div class="grid gap-3 p-3 lg:hidden">
                        @forelse ($recentPatrols as $log)
                            @php
                                $scanTime = $log->scanned_at?->timezone(config('app.timezone'));
                            @endphp
                            <article class="min-w-0 rounded-md border border-blue-100 p-3">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-blue-950">{{ $log->securityGuard?->name ?? 'Unknown card' }}</p>
                                        <p class="mt-1 truncate text-xs text-slate-500">{{ $log->securityGuard?->employee_no ?? $log->rfid_uid }}</p>
                                    </div>
                                    <span class="shrink-0 whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$log->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                        {{ str($log->status)->replace('_', ' ')->title() }}
                                    </span>
                                </div>
                                <dl class="mt-3 grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
                                    <div class="min-w-0">
                                        <dt class="text-xs font-semibold uppercase text-blue-800">Checkpoint</dt>
                                        <dd class="mt-1 truncate font-medium text-slate-900">{{ $log->checkpoint?->name ?? $log->checkpoint_code }}</dd>
                                    </div>
                                    <div class="min-w-0">
                                        <dt class="text-xs font-semibold uppercase text-blue-800">Time</dt>
                                        <dd class="mt-1 whitespace-nowrap">{{ $scanTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</dd>
                                    </div>
                                </dl>
                            </article>
                        @empty
                            <div class="rounded-md border border-blue-100 px-5 py-8 text-center text-slate-500">No patrol logs yet.</div>
                        @endforelse
                    </div>

                    <div class="hidden overflow-x-auto lg:block">
                        <table class="min-w-[48rem] divide-y divide-blue-100 text-sm">
                            <thead class="bg-blue-50/70 text-left text-xs font-semibold uppercase text-blue-800">
                                <tr>
                                    <th class="whitespace-nowrap px-5 py-3">Guard</th>
                                    <th class="whitespace-nowrap px-5 py-3">Checkpoint</th>
                                    <th class="whitespace-nowrap px-5 py-3">Verification</th>
                                    <th class="whitespace-nowrap px-5 py-3">Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-blue-50">
                                @forelse ($recentPatrols as $log)
                                    @php
                                        $scanTime = $log->scanned_at?->timezone(config('app.timezone'));
                                    @endphp
                                    <tr>
                                        <td class="px-5 py-4">
                                            <div class="font-medium text-slate-900">{{ $log->securityGuard?->name ?? 'Unknown card' }}</div>
                                            <div class="mt-1 text-xs text-slate-500">{{ $log->securityGuard?->employee_no ?? $log->rfid_uid }}</div>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600">{{ $log->checkpoint?->name ?? $log->checkpoint_code }}</td>
                                        <td class="px-5 py-4">
                                            <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$log->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                                {{ str($log->status)->replace('_', ' ')->title() }}
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $scanTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-slate-500">No patrol logs yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                    <div class="border-t border-blue-100 px-5 py-4">
                        {{ $recentPatrols->links() }}
                    </div>
                </div>

                <div class="rounded-md border border-blue-100 bg-white p-5 shadow-sm">
                    <div class="flex items-center justify-between">
                        <h3 class="font-semibold text-blue-950">Checkpoint Activity</h3>
                        <a href="{{ route('checkpoints.index') }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">Manage</a>
                    </div>
                    <div class="mt-5 space-y-4">
                        @forelse ($checkpointActivity as $checkpoint)
                            @php
                                $max = max(1, $checkpointActivity->max('valid_patrols_count'));
                                $width = ($checkpoint->valid_patrols_count / $max) * 100;
                            @endphp
                            <div>
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-medium text-slate-700">{{ $checkpoint->code }}</span>
                                    <span class="text-slate-500">{{ $checkpoint->valid_patrols_count }}</span>
                                </div>
                                <div class="mt-2 h-2 rounded-full bg-blue-100">
                                    <div class="h-2 rounded-full bg-blue-600" style="width: {{ $width }}%"></div>
                                </div>
                            </div>
                        @empty
                            <p class="text-sm text-slate-500">No checkpoint data yet.</p>
                        @endforelse
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-blue-100 px-5 py-4">
                    <h3 class="font-semibold text-blue-950">Recent Incidents</h3>
                    <a href="{{ route('incidents.index') }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">Review</a>
                </div>

                <div class="grid gap-3 p-3 lg:hidden">
                    @forelse ($recentIncidents as $incident)
                        @php
                            $incidentTime = $incident->incident_at?->timezone(config('app.timezone'));
                        @endphp
                        <article class="min-w-0 rounded-md border border-blue-100 p-3">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-blue-950">{{ $incident->category }}</p>
                                    <p class="mt-1 truncate text-xs text-slate-500">{{ $incident->checkpoint?->name ?? 'Unassigned' }}</p>
                                </div>
                                <span class="shrink-0 whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $incidentStatusClasses[$incident->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                    {{ str($incident->status)->replace('_', ' ')->title() }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $priorityClasses[$incident->priority] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                    {{ ucfirst($incident->priority) }}
                                </span>
                                <span class="text-xs text-slate-500">{{ $incidentTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</span>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-md border border-blue-100 px-5 py-8 text-center text-slate-500">No incidents submitted.</div>
                    @endforelse
                </div>

                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-[56rem] divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-semibold uppercase text-blue-800">
                            <tr>
                                <th class="whitespace-nowrap px-5 py-3">Category</th>
                                <th class="whitespace-nowrap px-5 py-3">Location</th>
                                <th class="whitespace-nowrap px-5 py-3">Priority</th>
                                <th class="whitespace-nowrap px-5 py-3">Status</th>
                                <th class="whitespace-nowrap px-5 py-3">Reported</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            @forelse ($recentIncidents as $incident)
                                @php
                                    $incidentTime = $incident->incident_at?->timezone(config('app.timezone'));
                                @endphp
                                <tr>
                                    <td class="px-5 py-4 font-medium text-slate-900">{{ $incident->category }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $incident->checkpoint?->name ?? 'Unassigned' }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $priorityClasses[$incident->priority] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ ucfirst($incident->priority) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $incidentStatusClasses[$incident->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ str($incident->status)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $incidentTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-slate-500">No incidents submitted.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
