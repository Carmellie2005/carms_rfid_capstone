<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">RFID Reader Status</h2>
                <p class="mt-1 text-sm text-blue-600">Checkpoint devices and recent scan diagnostics</p>
            </div>
            <a href="{{ route('checkpoints.index') }}" class="inline-flex w-full items-center justify-center rounded-md border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 sm:w-auto">
                Manage Checkpoints
            </a>
        </div>
    </x-slot>

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            <section class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ([
                    ['label' => 'Readers', 'value' => $summary['total'], 'tone' => 'blue'],
                    ['label' => 'Online', 'value' => $summary['online'], 'tone' => 'emerald'],
                    ['label' => 'Offline', 'value' => $summary['offline'], 'tone' => 'amber'],
                    ['label' => 'Needs Review', 'value' => $summary['troubleScans'], 'tone' => 'red'],
                ] as $item)
                    <div class="rounded-lg border border-blue-100 bg-white p-5 shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $item['label'] }}</p>
                        <p class="mt-2 text-3xl font-bold text-blue-950">{{ $item['value'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="grid gap-4 lg:grid-cols-2">
                @forelse ($checkpoints as $checkpoint)
                    @php
                        $state = $checkpoint->reader_state;
                        $stateConfig = [
                            'online' => ['label' => 'Online', 'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
                            'offline' => ['label' => 'Offline', 'class' => 'bg-amber-50 text-amber-700 ring-amber-200'],
                            'inactive' => ['label' => 'Inactive', 'class' => 'bg-slate-50 text-slate-600 ring-slate-200'],
                            'no_device' => ['label' => 'No Device', 'class' => 'bg-red-50 text-red-700 ring-red-200'],
                        ][$state] ?? ['label' => 'Unknown', 'class' => 'bg-slate-50 text-slate-600 ring-slate-200'];
                        $latest = $checkpoint->latestPatrolLog;
                    @endphp

                    <article class="rounded-lg border border-blue-100 bg-white p-5 shadow-sm">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-mono text-xs font-semibold uppercase text-blue-700">{{ $checkpoint->code }}</p>
                                <h3 class="mt-1 text-lg font-semibold text-blue-950">{{ $checkpoint->name }}</h3>
                                <p class="mt-1 text-sm text-slate-500">{{ $checkpoint->location }}</p>
                            </div>
                            <span class="inline-flex w-fit rounded-full px-3 py-1 text-xs font-semibold ring-1 {{ $stateConfig['class'] }}">
                                {{ $stateConfig['label'] }}
                            </span>
                        </div>

                        <dl class="mt-5 grid gap-3 sm:grid-cols-2">
                            <div class="rounded-md bg-blue-50/60 p-3">
                                <dt class="text-xs font-semibold uppercase text-blue-800">Device UID</dt>
                                <dd class="mt-1 font-mono text-sm text-slate-800">{{ $checkpoint->device_uid ?: 'Not assigned' }}</dd>
                            </div>
                            <div class="rounded-md bg-blue-50/60 p-3">
                                <dt class="text-xs font-semibold uppercase text-blue-800">Last Seen</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-800">
                                    {{ $checkpoint->reader_seen_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? 'No reader activity' }}
                                </dd>
                            </div>
                            <div class="rounded-md bg-blue-50/60 p-3">
                                <dt class="text-xs font-semibold uppercase text-blue-800">Reader IP</dt>
                                <dd class="mt-1 font-mono text-sm text-slate-800">{{ $checkpoint->reader_last_ip ?: 'Not recorded' }}</dd>
                            </div>
                            <div class="rounded-md bg-blue-50/60 p-3">
                                <dt class="text-xs font-semibold uppercase text-blue-800">Latest Scan</dt>
                                <dd class="mt-1 text-sm font-medium text-slate-800">
                                    {{ $latest?->scanned_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? 'No scan yet' }}
                                </dd>
                            </div>
                        </dl>

                        @if ($checkpoint->reader_last_message)
                            <p class="mt-4 rounded-md border border-blue-100 bg-white px-3 py-2 text-sm text-slate-600">
                                {{ $checkpoint->reader_last_message }}
                            </p>
                        @endif
                    </article>
                @empty
                    <div class="rounded-lg border border-blue-100 bg-white px-5 py-8 text-center text-slate-500 shadow-sm lg:col-span-2">
                        No checkpoints registered.
                    </div>
                @endforelse
            </section>

            <section class="rounded-lg border border-blue-100 bg-white shadow-sm">
                <div class="border-b border-blue-100 px-5 py-4">
                    <h3 class="text-base font-semibold text-blue-950">Scan Troubleshooting Panel</h3>
                    <p class="mt-1 text-sm text-slate-500">Recent scans with system diagnosis and suggested action</p>
                </div>

                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full divide-y divide-blue-100">
                        <thead class="bg-blue-50/70">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Time</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Reader</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">RFID / Guard</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Status</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Diagnosis</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            @forelse ($recentScans as $item)
                                @php
                                    $log = $item['log'];
                                    $severityClass = [
                                        'ok' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
                                        'warning' => 'bg-amber-50 text-amber-700 ring-amber-200',
                                        'danger' => 'bg-red-50 text-red-700 ring-red-200',
                                    ][$item['severity']] ?? 'bg-slate-50 text-slate-600 ring-slate-200';
                                @endphp
                                <tr>
                                    <td class="px-5 py-4 text-sm text-slate-600">{{ $log->scanned_at?->timezone('Asia/Manila')->format('M d, h:i A') }}</td>
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-slate-900">{{ $log->checkpoint?->name ?? 'Unknown' }}</div>
                                        <div class="font-mono text-xs text-slate-500">{{ $log->checkpoint?->device_uid ?? $log->checkpoint_code }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-mono text-sm text-slate-800">{{ $log->rfid_uid }}</div>
                                        <div class="text-xs text-slate-500">{{ $log->securityGuard?->employee_no ?? 'No guard match' }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $severityClass }}">
                                            {{ str($log->status)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td class="max-w-xs px-5 py-4 text-sm text-slate-600">{{ $log->notes ?: 'No diagnosis recorded.' }}</td>
                                    <td class="max-w-xs px-5 py-4 text-sm font-medium text-slate-700">{{ $item['suggestion'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-500">No RFID scans recorded yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-3 p-4 lg:hidden">
                    @forelse ($recentScans as $item)
                        @php $log = $item['log']; @endphp
                        <article class="rounded-lg border border-blue-100 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="font-mono text-sm font-semibold text-blue-950">{{ $log->rfid_uid }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $log->scanned_at?->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
                                </div>
                                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
                                    {{ str($log->status)->replace('_', ' ')->title() }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm text-slate-600">{{ $log->notes ?: 'No diagnosis recorded.' }}</p>
                            <p class="mt-2 text-sm font-semibold text-slate-800">{{ $item['suggestion'] }}</p>
                        </article>
                    @empty
                        <div class="rounded-lg border border-blue-100 px-5 py-8 text-center text-slate-500">No RFID scans recorded yet.</div>
                    @endforelse
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
