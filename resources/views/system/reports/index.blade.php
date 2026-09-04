<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">{{ __('Reports') }}</h2>
                <p class="mt-1 text-sm text-blue-600">Patrol summaries, verification results, and incident documentation</p>
            </div>
            <button onclick="window.print()" class="inline-flex w-full items-center justify-center rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto print:hidden" type="button">
                Print Report
            </button>
        </div>
    </x-slot>

    @php
        $statusClasses = [
            'valid' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'verified' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'resolved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'suspicious' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'profile_incomplete' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'outside_schedule' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'pending_face' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'submitted' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'under_review' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'invalid' => 'bg-red-50 text-red-700 ring-red-200',
            'failed' => 'bg-red-50 text-red-700 ring-red-200',
            'not_required' => 'bg-slate-50 text-slate-700 ring-slate-200',
            'expired' => 'bg-slate-50 text-slate-700 ring-slate-200',
        ];

        $priorityClasses = [
            'low' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'normal' => 'bg-slate-50 text-slate-700 ring-slate-200',
            'high' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'critical' => 'bg-red-50 text-red-700 ring-red-200',
        ];

        $summaryCards = [
            ['label' => 'Valid Patrols', 'value' => $summary['valid'], 'cardClass' => 'border-emerald-100 bg-emerald-50/60', 'labelClass' => 'text-emerald-700', 'valueClass' => 'text-emerald-900'],
            ['label' => 'Suspicious', 'value' => $summary['suspicious'], 'cardClass' => 'border-amber-100 bg-amber-50/60', 'labelClass' => 'text-amber-700', 'valueClass' => 'text-amber-900'],
            ['label' => 'Invalid Scans', 'value' => $summary['invalid'], 'cardClass' => 'border-red-100 bg-red-50/60', 'labelClass' => 'text-red-700', 'valueClass' => 'text-red-900'],
            ['label' => 'Profile Incomplete', 'value' => $summary['profileIncomplete'], 'cardClass' => 'border-violet-100 bg-violet-50/60', 'labelClass' => 'text-violet-700', 'valueClass' => 'text-violet-900'],
            ['label' => 'Outside Schedule', 'value' => $summary['outsideSchedule'], 'cardClass' => 'border-orange-100 bg-orange-50/60', 'labelClass' => 'text-orange-700', 'valueClass' => 'text-orange-900'],
            ['label' => 'Incidents', 'value' => $summary['incidents'], 'cardClass' => 'border-blue-100 bg-white', 'labelClass' => 'text-blue-700', 'valueClass' => 'text-blue-950'],
        ];
    @endphp

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('reports.index') }}" class="grid gap-4 rounded-md border border-blue-100 bg-white p-4 shadow-sm md:grid-cols-[1fr_1fr_auto] print:hidden">
                <div>
                    <label for="from" class="block text-xs font-semibold uppercase text-blue-800">From</label>
                    <input id="from" name="from" type="date" value="{{ request('from', $from->toDateString()) }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="to" class="block text-xs font-semibold uppercase text-blue-800">To</label>
                    <input id="to" name="to" type="date" value="{{ request('to', $to->toDateString()) }}" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="flex items-end">
                    <button class="w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800" type="submit">Generate</button>
                </div>
            </form>

            <section class="rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex items-start gap-4">
                    <x-application-logo class="h-14 w-14 shrink-0" />
                    <div>
                        <h3 class="text-lg font-semibold text-blue-950">SLSU Bontoc Patrol</h3>
                        <p class="mt-1 text-sm text-slate-600">Report period: {{ $from->format('M d, Y') }} to {{ $to->format('M d, Y') }}</p>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-6">
                    @foreach ($summaryCards as $card)
                        <div class="min-h-[5.5rem] rounded-md border p-3 shadow-sm sm:p-4 {{ $card['cardClass'] }}">
                            <p class="truncate whitespace-nowrap text-[0.7rem] font-semibold uppercase tracking-wide sm:text-xs {{ $card['labelClass'] }}">{{ $card['label'] }}</p>
                            <p class="mt-2 text-2xl font-semibold {{ $card['valueClass'] }}">{{ $card['value'] }}</p>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm">
                <div class="border-b border-blue-100 px-5 py-4">
                    <h3 class="font-semibold text-blue-950">Patrol Summary</h3>
                </div>

                <x-pagination-panel :paginator="$patrols" label="patrol report records" page-label="Patrol page" class="border-b border-blue-50 px-5 py-3" />

                <div class="grid gap-3 p-3 lg:hidden print:hidden">
                    @forelse ($patrols as $patrol)
                        @php
                            $scanTime = $patrol->scanned_at?->timezone(config('app.timezone'));
                        @endphp
                        <article class="min-w-0 rounded-md border border-blue-100 p-3 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="text-xs font-semibold uppercase text-blue-800">Date/Time</p>
                                    <p class="mt-1 text-sm font-semibold text-blue-950">{{ $scanTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</p>
                                </div>
                                <span class="shrink-0 whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$patrol->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                    {{ str($patrol->status)->replace('_', ' ')->title() }}
                                </span>
                            </div>

                            <dl class="mt-3 grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
                                <div class="min-w-0">
                                    <dt class="text-xs font-semibold uppercase text-blue-800">Guard</dt>
                                    <dd class="mt-1 truncate font-medium text-slate-900">{{ $patrol->securityGuard?->name ?? 'Unknown' }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs font-semibold uppercase text-blue-800">Checkpoint</dt>
                                    <dd class="mt-1 truncate font-medium text-slate-900">{{ $patrol->checkpoint?->name ?? $patrol->checkpoint_code }}</dd>
                                </div>
                            </dl>

                            <div class="mt-3 flex flex-wrap gap-2">
                                <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$patrol->rfid_status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                    RFID: {{ str($patrol->rfid_status)->replace('_', ' ')->title() }}
                                </span>
                                <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$patrol->facial_status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                    Face: {{ str($patrol->facial_status)->replace('_', ' ')->title() }}
                                </span>
                            </div>
                        </article>
                    @empty
                        <div class="rounded-md border border-blue-100 px-5 py-8 text-center text-slate-500">No patrol records for this period.</div>
                    @endforelse
                </div>

                <div class="hidden overflow-x-auto lg:block print:block">
                    <table class="min-w-[56rem] divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-semibold uppercase text-blue-800">
                            <tr>
                                <th class="whitespace-nowrap px-5 py-3">Date/Time</th>
                                <th class="whitespace-nowrap px-5 py-3">Guard</th>
                                <th class="whitespace-nowrap px-5 py-3">Checkpoint</th>
                                <th class="whitespace-nowrap px-5 py-3">RFID</th>
                                <th class="whitespace-nowrap px-5 py-3">Face</th>
                                <th class="whitespace-nowrap px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            @forelse ($patrols as $patrol)
                                @php
                                    $scanTime = $patrol->scanned_at?->timezone(config('app.timezone'));
                                @endphp
                                <tr>
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $scanTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</td>
                                    <td class="px-5 py-4 text-slate-900">{{ $patrol->securityGuard?->name ?? 'Unknown' }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $patrol->checkpoint?->name ?? $patrol->checkpoint_code }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$patrol->rfid_status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ str($patrol->rfid_status)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$patrol->facial_status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ str($patrol->facial_status)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$patrol->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ str($patrol->status)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-500">No patrol records for this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($patrols->hasPages())
                    <x-pagination-panel :paginator="$patrols" label="patrol report records" page-label="Patrol page" class="border-t border-blue-100 px-5 py-4" />
                @endif
            </section>

            <section class="overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm">
                <div class="border-b border-blue-100 px-5 py-4">
                    <h3 class="font-semibold text-blue-950">Incident Summary</h3>
                </div>

                <x-pagination-panel :paginator="$incidents" label="incident report records" page-label="Incident page" class="border-b border-blue-50 px-5 py-3" />

                <div class="grid gap-3 p-3 lg:hidden print:hidden">
                    @forelse ($incidents as $incident)
                        @php
                            $incidentTime = $incident->incident_at?->timezone(config('app.timezone'));
                        @endphp
                        <article class="min-w-0 rounded-md border border-blue-100 p-3 shadow-sm">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-blue-950">{{ $incident->category }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $incidentTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</p>
                                </div>
                                <span class="shrink-0 whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$incident->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                    {{ str($incident->status)->replace('_', ' ')->title() }}
                                </span>
                            </div>

                            <dl class="mt-3 grid gap-3 text-sm text-slate-600 sm:grid-cols-2">
                                <div class="min-w-0">
                                    <dt class="text-xs font-semibold uppercase text-blue-800">Location</dt>
                                    <dd class="mt-1 truncate font-medium text-slate-900">{{ $incident->checkpoint?->name ?? 'Unassigned' }}</dd>
                                </div>
                                <div class="min-w-0">
                                    <dt class="text-xs font-semibold uppercase text-blue-800">Priority</dt>
                                    <dd class="mt-1">
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $priorityClasses[$incident->priority] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ ucfirst($incident->priority) }}
                                        </span>
                                    </dd>
                                </div>
                            </dl>

                            <p class="mt-3 max-h-[4.5rem] overflow-hidden text-sm text-slate-600">{{ $incident->description }}</p>
                            <a href="{{ route('incidents.pdf', $incident) }}" class="mt-3 inline-flex items-center justify-center whitespace-nowrap rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                                Download
                            </a>
                        </article>
                    @empty
                        <div class="rounded-md border border-blue-100 px-5 py-8 text-center text-slate-500">No incident records for this period.</div>
                    @endforelse
                </div>

                <div class="hidden overflow-x-auto lg:block print:block">
                    <table class="min-w-[64rem] divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-semibold uppercase text-blue-800">
                            <tr>
                                <th class="whitespace-nowrap px-5 py-3">Date/Time</th>
                                <th class="whitespace-nowrap px-5 py-3">Category</th>
                                <th class="whitespace-nowrap px-5 py-3">Location</th>
                                <th class="whitespace-nowrap px-5 py-3">Priority</th>
                                <th class="whitespace-nowrap px-5 py-3">Status</th>
                                <th class="whitespace-nowrap px-5 py-3">Description</th>
                                <th class="whitespace-nowrap px-5 py-3 print:hidden">PDF</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            @forelse ($incidents as $incident)
                                @php
                                    $incidentTime = $incident->incident_at?->timezone(config('app.timezone'));
                                @endphp
                                <tr class="align-top">
                                    <td class="whitespace-nowrap px-5 py-4 text-slate-600">{{ $incidentTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</td>
                                    <td class="px-5 py-4 font-medium text-slate-900">{{ $incident->category }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $incident->checkpoint?->name ?? 'Unassigned' }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $priorityClasses[$incident->priority] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ ucfirst($incident->priority) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$incident->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ str($incident->status)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4 text-slate-600">{{ $incident->description }}</td>
                                    <td class="px-5 py-4 print:hidden">
                                        <a href="{{ route('incidents.pdf', $incident) }}" class="inline-flex items-center justify-center rounded-md border border-blue-200 px-3 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                                            Download
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-5 py-8 text-center text-slate-500">No incident records for this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                @if ($incidents->hasPages())
                    <x-pagination-panel :paginator="$incidents" label="incident report records" page-label="Incident page" class="border-t border-blue-100 px-5 py-4" />
                @endif
            </section>
        </div>
    </div>
</x-app-layout>
