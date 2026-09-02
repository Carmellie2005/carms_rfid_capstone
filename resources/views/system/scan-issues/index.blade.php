<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-blue-950">Scan Issues</h2>
            <p class="mt-1 text-sm text-blue-600">Unregistered RFID cards, invalid scans, and scans that need review</p>
        </div>
    </x-slot>

    @php
        $statusClasses = [
            'invalid' => 'bg-red-50 text-red-700 ring-red-200',
            'suspicious' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'pending_face' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'profile_incomplete' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'outside_schedule' => 'bg-orange-50 text-orange-700 ring-orange-200',
            'expired' => 'bg-slate-50 text-slate-700 ring-slate-200',
        ];

        $severityClasses = [
            'ok' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'warning' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'danger' => 'bg-red-50 text-red-700 ring-red-200',
        ];

        $summaryCards = [
            ['label' => 'Total Issues', 'value' => $summary['total'], 'cardClass' => 'border-blue-100 bg-white', 'labelClass' => 'text-blue-700', 'valueClass' => 'text-blue-950'],
            ['label' => 'Unregistered RFID', 'value' => $summary['unregistered'], 'cardClass' => 'border-red-100 bg-red-50/60', 'labelClass' => 'text-red-700', 'valueClass' => 'text-red-900'],
            ['label' => 'Invalid Scans', 'value' => $summary['invalid'], 'cardClass' => 'border-amber-100 bg-amber-50/60', 'labelClass' => 'text-amber-700', 'valueClass' => 'text-amber-900'],
            ['label' => 'Face Review', 'value' => $summary['needsFace'], 'cardClass' => 'border-violet-100 bg-violet-50/60', 'labelClass' => 'text-violet-700', 'valueClass' => 'text-violet-900'],
        ];
    @endphp

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ($summaryCards as $card)
                    <div class="min-h-[5.75rem] rounded-md border p-3 shadow-sm sm:p-5 {{ $card['cardClass'] }}">
                        <p class="truncate whitespace-nowrap text-[0.7rem] font-semibold uppercase tracking-wide sm:text-xs {{ $card['labelClass'] }}">{{ $card['label'] }}</p>
                        <p class="mt-2 text-2xl font-semibold sm:text-3xl {{ $card['valueClass'] }}">{{ $card['value'] }}</p>
                    </div>
                @endforeach
            </section>

            <form method="GET" action="{{ route('scan-issues.index') }}" class="grid gap-3 rounded-md border border-blue-100 bg-white p-3 shadow-sm md:grid-cols-[minmax(12rem,18rem)_auto]">
                <div>
                    <label for="status" class="block text-xs font-semibold uppercase text-blue-800">Status</label>
                    <select id="status" name="status" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All issue statuses</option>
                        @foreach ($statusOptions as $value => $label)
                            <option value="{{ $value }}" @selected($selectedStatus === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="flex flex-wrap items-end gap-2">
                    <button class="h-9 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50" type="submit">Filter</button>
                    <a href="{{ route('scan-issues.index') }}" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">Clear</a>
                </div>
            </form>

            <section class="overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm">
                <div class="border-b border-blue-100 px-5 py-4">
                    <h3 class="text-base font-semibold text-blue-950">Scan Troubleshooting</h3>
                    <p class="mt-1 text-sm text-slate-500">Recent scan records with diagnosis and suggested action</p>
                </div>

                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-[72rem] divide-y divide-blue-100">
                        <thead class="bg-blue-50/70">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Time</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Issue</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">RFID / Guard</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Reader</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Status</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Diagnosis</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            @forelse ($scans as $item)
                                @php
                                    $log = $item['log'];
                                    $scanTime = $log->scanned_at?->timezone(config('app.timezone'));
                                    $statusClass = $statusClasses[$log->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200';
                                    $severityClass = $severityClasses[$item['severity']] ?? 'bg-slate-50 text-slate-700 ring-slate-200';
                                @endphp
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-5 py-4 text-sm text-slate-600">{{ $scanTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $severityClass }}">
                                            {{ $item['issueType'] }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-mono text-sm text-slate-800">{{ $log->rfid_uid }}</div>
                                        <div class="text-xs text-slate-500">{{ $log->securityGuard?->employee_no === 'UNKNOWN' ? 'No guard match' : ($log->securityGuard?->employee_no ?? 'No guard match') }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-slate-900">{{ $log->checkpoint?->name ?? 'Unknown reader' }}</div>
                                        <div class="font-mono text-xs text-slate-500">{{ $log->checkpoint?->device_uid ?? $log->checkpoint_code }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClass }}">
                                            {{ str($log->status)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td class="max-w-xs px-5 py-4 text-sm text-slate-600">{{ $log->notes ?: 'No diagnosis recorded.' }}</td>
                                    <td class="max-w-xs px-5 py-4 text-sm font-medium text-slate-700">{{ $item['suggestion'] }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-8 text-center text-slate-500">No scan issues found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-3 p-3 sm:p-4 lg:hidden">
                    @forelse ($scans as $item)
                        @php
                            $log = $item['log'];
                            $scanTime = $log->scanned_at?->timezone(config('app.timezone'));
                            $statusClass = $statusClasses[$log->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200';
                            $severityClass = $severityClasses[$item['severity']] ?? 'bg-slate-50 text-slate-700 ring-slate-200';
                        @endphp
                        <article class="min-w-0 rounded-md border border-blue-100 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate font-mono text-sm font-semibold text-blue-950">{{ $log->rfid_uid }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $scanTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</p>
                                </div>
                                <span class="max-w-[7rem] shrink-0 truncate whitespace-nowrap rounded-md px-2 py-1 text-[0.65rem] font-semibold ring-1 {{ $statusClass }}">
                                    {{ str($log->status)->replace('_', ' ')->title() }}
                                </span>
                            </div>
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                <span class="whitespace-nowrap rounded-md px-2 py-1 text-[0.65rem] font-semibold ring-1 {{ $severityClass }}">{{ $item['issueType'] }}</span>
                                <span class="whitespace-nowrap rounded-md bg-blue-50 px-2 py-1 text-[0.65rem] font-semibold text-blue-700 ring-1 ring-blue-200">{{ $log->checkpoint?->code ?? $log->checkpoint_code ?? 'No reader' }}</span>
                            </div>
                            <p class="mt-3 text-xs text-slate-600 sm:text-sm">{{ $log->notes ?: 'No diagnosis recorded.' }}</p>
                            <p class="mt-2 text-xs font-semibold text-slate-800 sm:text-sm">{{ $item['suggestion'] }}</p>
                        </article>
                    @empty
                        <div class="rounded-md border border-blue-100 px-5 py-8 text-center text-slate-500">No scan issues found.</div>
                    @endforelse
                </div>

                <div class="border-t border-blue-100 px-5 py-4">
                    {{ $scans->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
