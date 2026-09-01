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
    @endphp

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800">
                    {{ session('status') }}
                </div>
            @endif

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                    <p class="text-sm font-medium text-slate-500">Active Guards</p>
                    <p class="mt-3 text-3xl font-semibold text-blue-950">{{ $stats['activeGuards'] }}</p>
                </div>
                <div class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                    <p class="text-sm font-medium text-slate-500">Checkpoints</p>
                    <p class="mt-3 text-3xl font-semibold text-blue-950">{{ $stats['activeCheckpoints'] }}</p>
                </div>
                <div class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                    <p class="text-sm font-medium text-slate-500">Today Patrols</p>
                    <p class="mt-3 text-3xl font-semibold text-blue-950">{{ $stats['todayPatrols'] }}</p>
                </div>
                <div class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                    <p class="text-sm font-medium text-slate-500">Open Incidents</p>
                    <p class="mt-3 text-3xl font-semibold text-blue-950">{{ $stats['openIncidents'] }}</p>
                </div>
                <div class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                    <p class="text-sm font-medium text-slate-500">For Review</p>
                    <p class="mt-3 text-3xl font-semibold text-blue-950">{{ $stats['suspiciousScans'] }}</p>
                </div>
            </section>

            <section
                x-data="dashboardCharts(@js($analytics))"
                x-init="render()"
                x-on:theme-changed.window="render()"
                class="grid gap-6 xl:grid-cols-2"
            >
                <div class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-blue-950">Patrol Trend</h3>
                            <p class="text-sm text-slate-500">Total checkpoint scans in the last 7 days</p>
                        </div>
                    </div>
                    <div class="mt-5 h-64 sm:h-72">
                        <canvas x-ref="patrolTrendChart" aria-label="Patrol trend chart"></canvas>
                    </div>
                </div>

                <div class="grid gap-6 lg:grid-cols-2">
                    <div class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                        <h3 class="font-semibold text-blue-950">Scan Status</h3>
                        <p class="text-sm text-slate-500">Valid, review, invalid, incomplete, and outside-schedule scans</p>
                        <div class="relative mt-5 h-64 sm:h-72">
                            <canvas x-ref="scanStatusChart" aria-label="Scan status chart"></canvas>
                            @if (collect($analytics['scanStatus']['data'])->sum() === 0)
                                <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm font-medium text-slate-400">
                                    No scan data yet
                                </div>
                            @endif
                        </div>
                    </div>

                    <div class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                        <h3 class="font-semibold text-blue-950">Incident Priority</h3>
                        <p class="text-sm text-slate-500">Submitted incidents by priority</p>
                        <div class="relative mt-5 h-64 sm:h-72">
                            <canvas x-ref="incidentPriorityChart" aria-label="Incident priority chart"></canvas>
                            @if (collect($analytics['incidentPriority']['data'])->sum() === 0)
                                <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm font-medium text-slate-400">
                                    No incident data yet
                                </div>
                            @endif
                        </div>
                    </div>
                </div>

                <div class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-5 xl:col-span-2">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 class="font-semibold text-blue-950">Checkpoint Activity Analytics</h3>
                            <p class="text-sm text-slate-500">Valid patrols recorded per active checkpoint</p>
                        </div>
                        <a href="{{ route('checkpoints.index') }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">Manage checkpoints</a>
                    </div>
                    <div class="relative mt-5 h-64 sm:h-72">
                        <canvas x-ref="checkpointActivityChart" aria-label="Checkpoint activity chart"></canvas>
                        @if (collect($analytics['checkpointActivity']['data'])->sum() === 0)
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center text-sm font-medium text-slate-400">
                                No checkpoint activity yet
                            </div>
                        @endif
                    </div>
                </div>
            </section>

            <section class="grid gap-6 xl:grid-cols-[1fr_360px]">
                <div class="overflow-hidden rounded-lg border border-blue-100 bg-white shadow-sm">
                    <div class="flex items-center justify-between border-b border-blue-100 px-5 py-4">
                        <h3 class="font-semibold text-blue-950">Recent Patrol Logs</h3>
                        <a href="{{ route('patrol-logs.index') }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">View all</a>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-blue-100 text-sm">
                            <thead class="bg-blue-50/70 text-left text-xs font-semibold uppercase text-blue-800">
                                <tr>
                                    <th class="px-5 py-3">Guard</th>
                                    <th class="px-5 py-3">Checkpoint</th>
                                    <th class="px-5 py-3">Verification</th>
                                    <th class="px-5 py-3">Time</th>
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
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$log->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                                {{ str($log->status)->replace('_', ' ')->title() }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-slate-600">{{ $scanTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="px-5 py-8 text-center text-slate-500">No patrol logs yet.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="rounded-lg border border-blue-100 bg-white p-5 shadow-sm">
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

            <section class="overflow-hidden rounded-lg border border-blue-100 bg-white shadow-sm">
                <div class="flex items-center justify-between border-b border-blue-100 px-5 py-4">
                    <h3 class="font-semibold text-blue-950">Recent Incidents</h3>
                    <a href="{{ route('incidents.index') }}" class="text-sm font-medium text-blue-700 hover:text-blue-900">Review</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-semibold uppercase text-blue-800">
                            <tr>
                                <th class="px-5 py-3">Category</th>
                                <th class="px-5 py-3">Location</th>
                                <th class="px-5 py-3">Priority</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Reported</th>
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
                                    <td class="px-5 py-4 text-slate-600">{{ ucfirst($incident->priority) }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ str($incident->status)->replace('_', ' ')->title() }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $incidentTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</td>
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
