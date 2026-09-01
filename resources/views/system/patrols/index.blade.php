<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">{{ $isSupervisor ? __('Patrol Logs') : __('My Patrol Logs') }}</h2>
                <p class="mt-1 text-sm text-blue-600">RFID scans, facial verification results, and checklist records</p>
            </div>
            @unless ($isSupervisor)
                <a href="{{ route('patrol.scan') }}" class="inline-flex w-full items-center justify-center rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto">
                    New Scan
                </a>
            @endunless
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
        ];
    @endphp

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @php
                $exportQuery = request()->only(['status', 'guard_id', 'checkpoint_id', 'date']);
            @endphp

            <form method="GET" action="{{ route('patrol-logs.index') }}" class="grid gap-3 rounded-lg border border-blue-100 bg-white p-3 shadow-sm {{ $isSupervisor ? 'md:grid-cols-2 xl:grid-cols-[minmax(150px,0.8fr)_minmax(170px,1fr)_minmax(150px,0.8fr)_minmax(140px,0.75fr)_auto]' : 'md:grid-cols-2 xl:grid-cols-[minmax(150px,0.8fr)_minmax(150px,0.8fr)_minmax(140px,0.75fr)_auto]' }}">
                <div>
                    <label for="status" class="block text-xs font-semibold uppercase text-blue-800">Status</label>
                    <select id="status" name="status" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All</option>
                        @foreach (['valid' => 'Valid', 'suspicious' => 'Suspicious', 'invalid' => 'Invalid', 'pending_face' => 'Pending Face', 'profile_incomplete' => 'Profile Incomplete', 'outside_schedule' => 'Outside Schedule', 'expired' => 'Expired'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($isSupervisor)
                    <div>
                        <label for="guard_id" class="block text-xs font-semibold uppercase text-blue-800">Guard</label>
                        <select id="guard_id" name="guard_id" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">All</option>
                            @foreach ($guards as $guard)
                                <option value="{{ $guard->id }}" @selected((string) request('guard_id') === (string) $guard->id)>{{ $guard->name }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
                <div>
                    <label for="checkpoint_id" class="block text-xs font-semibold uppercase text-blue-800">Checkpoint</label>
                    <select id="checkpoint_id" name="checkpoint_id" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All</option>
                        @foreach ($checkpoints as $checkpoint)
                            <option value="{{ $checkpoint->id }}" @selected((string) request('checkpoint_id') === (string) $checkpoint->id)>{{ $checkpoint->code }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date" class="block text-xs font-semibold uppercase text-blue-800">Date Filter</label>
                    <input id="date" name="date" type="date" value="{{ request('date') }}" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="flex flex-wrap items-end gap-2 {{ $isSupervisor ? 'md:col-span-2 xl:col-span-1' : 'md:col-span-2 xl:col-span-1' }} xl:self-end xl:justify-end">
                    <button class="h-9 rounded-md bg-blue-700 px-3 text-xs font-semibold text-white hover:bg-blue-800" type="submit">Filter</button>
                    <a href="{{ route('patrol-logs.index') }}" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">Clear</a>
                    <a href="{{ route('patrol-logs.pdf', $exportQuery) }}" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                        Download PDF
                    </a>
                    <a href="{{ route('patrol-logs.pdf', array_merge($exportQuery, ['print' => 1])) }}" target="_blank" rel="noopener" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                        Print PDF
                    </a>
                </div>
            </form>

            <div class="grid gap-3 md:hidden">
                @forelse ($logs as $log)
                    @php
                        $scanTime = $log->scanned_at?->timezone(config('app.timezone'));
                    @endphp
                    <article class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase text-blue-800">{{ $scanTime?->format('M d, Y') ?? 'No date' }}</p>
                                <p class="mt-1 text-sm font-semibold text-blue-950">{{ $scanTime?->format('h:i A') ?? 'No time' }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$log->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                {{ str($log->status)->replace('_', ' ')->title() }}
                            </span>
                        </div>

                        <dl class="mt-4 grid gap-3 text-sm text-slate-600">
                            <div>
                                <dt class="text-xs font-semibold uppercase text-blue-800">Guard</dt>
                                <dd class="mt-1 font-medium text-slate-900">{{ $log->securityGuard?->name ?? 'Unknown' }}</dd>
                                <dd class="text-xs text-slate-500">{{ $log->securityGuard?->employee_no ?? 'No guard match' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase text-blue-800">Checkpoint</dt>
                                <dd class="mt-1 font-medium text-slate-900">{{ $log->checkpoint?->name ?? 'Unknown' }}</dd>
                                <dd class="font-mono text-xs text-slate-500">{{ $log->checkpoint_code }}</dd>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <dt class="text-xs font-semibold uppercase text-blue-800">RFID</dt>
                                    <dd class="mt-1 font-mono">{{ $log->rfid_uid }}</dd>
                                </div>
                                <div>
                                    <dt class="text-xs font-semibold uppercase text-blue-800">Face</dt>
                                    <dd class="mt-1">{{ str($log->facial_status)->replace('_', ' ')->title() }}</dd>
                                </div>
                            </div>
                        </dl>

                        @if ($log->checklistResponse)
                            @php
                                $mobileFlags = \App\Support\PatrolChecklist::checkedLabels($log->checklistResponse);
                            @endphp
                            <div class="mt-4 flex flex-wrap gap-1">
                                @forelse ($mobileFlags as $label)
                                    <span class="rounded bg-blue-50 px-2 py-1 text-xs text-blue-700">{{ $label }}</span>
                                @empty
                                    <span class="text-xs text-slate-500">No checked checklist items</span>
                                @endforelse
                            </div>
                        @endif

                        <div class="mt-4 rounded-md bg-slate-50 p-3 text-sm text-slate-600">
                            <span class="font-semibold text-slate-800">Incident:</span>
                            @if ($log->incidentReport)
                                {{ $log->incidentReport->category }} - {{ str($log->incidentReport->status)->replace('_', ' ')->title() }}
                                <a href="{{ route('incidents.pdf', $log->incidentReport) }}" class="mt-2 inline-flex items-center justify-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                                    Download PDF
                                </a>
                            @else
                                None
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-lg border border-blue-100 bg-white px-5 py-8 text-center text-slate-500 shadow-sm">No patrol logs found.</div>
                @endforelse
            </div>

            <div class="hidden overflow-hidden rounded-lg border border-blue-100 bg-white shadow-sm md:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-semibold uppercase text-blue-800">
                            <tr>
                                <th class="px-5 py-3">Scan</th>
                                <th class="px-5 py-3">Guard</th>
                                <th class="px-5 py-3">Checkpoint</th>
                                <th class="px-5 py-3">RFID</th>
                                <th class="px-5 py-3">Face</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Checklist</th>
                                <th class="px-5 py-3">Incident</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            @forelse ($logs as $log)
                                @php
                                    $scanTime = $log->scanned_at?->timezone(config('app.timezone'));
                                @endphp
                                <tr class="align-top">
                                    <td class="px-5 py-4 whitespace-nowrap text-slate-600">
                                        <div>{{ $scanTime?->format('M d, Y') ?? 'No date' }}</div>
                                        <div class="text-xs">{{ $scanTime?->format('h:i A') ?? 'No time' }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-slate-900">{{ $log->securityGuard?->name ?? 'Unknown' }}</div>
                                        <div class="text-xs text-slate-500">{{ $log->securityGuard?->employee_no ?? 'No guard match' }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-slate-900">{{ $log->checkpoint?->name ?? 'Unknown' }}</div>
                                        <div class="text-xs font-mono text-slate-500">{{ $log->checkpoint_code }}</div>
                                    </td>
                                    <td class="px-5 py-4 font-mono text-slate-700">{{ $log->rfid_uid }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ str($log->facial_status)->replace('_', ' ')->title() }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$log->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ str($log->status)->replace('_', ' ')->title() }}
                                        </span>
                                        @if ($log->notes)
                                            <div class="mt-2 max-w-xs text-xs text-slate-500">{{ $log->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-slate-600">
                                        @if ($log->checklistResponse)
                                            @php
                                                $flags = \App\Support\PatrolChecklist::checkedLabels($log->checklistResponse);
                                            @endphp
                                            <div class="flex max-w-xs flex-wrap gap-1">
                                                @forelse ($flags as $label)
                                                    <span class="rounded bg-blue-50 px-2 py-1 text-xs text-blue-700">{{ $label }}</span>
                                                @empty
                                                    <span class="text-xs text-slate-500">No checked items</span>
                                                @endforelse
                                            </div>
                                        @else
                                            <span class="text-xs text-slate-500">No checklist</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-slate-600">
                                        @if ($log->incidentReport)
                                            <span class="font-medium text-slate-900">{{ $log->incidentReport->category }}</span>
                                            <div class="text-xs">{{ str($log->incidentReport->status)->replace('_', ' ')->title() }}</div>
                                            <a href="{{ route('incidents.pdf', $log->incidentReport) }}" class="mt-2 inline-flex items-center justify-center rounded-md border border-blue-200 px-2.5 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                                                PDF
                                            </a>
                                        @else
                                            <span class="text-xs text-slate-500">None</span>
                                        @endif
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-8 text-center text-slate-500">No patrol logs found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{ $logs->links() }}
        </div>
    </div>
</x-app-layout>
