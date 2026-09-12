<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">{{ __('Reports') }}</h2>
                <p class="mt-1 text-sm text-blue-600">Patrol summaries, checklist proof, and incident documentation</p>
            </div>
            <button onclick="window.print()" class="inline-flex w-full items-center justify-center rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto print:hidden" type="button">
                Print Report
            </button>
        </div>
    </x-slot>

    @php
        $statusClasses = [
            'valid' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/35 dark:text-emerald-200 dark:ring-emerald-400/45',
            'verified' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/35 dark:text-emerald-200 dark:ring-emerald-400/45',
            'resolved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/35 dark:text-emerald-200 dark:ring-emerald-400/45',
            'suspicious' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/35 dark:text-amber-200 dark:ring-amber-400/45',
            'profile_incomplete' => 'bg-violet-50 text-violet-700 ring-violet-200 dark:bg-violet-950/35 dark:text-violet-200 dark:ring-violet-400/45',
            'outside_schedule' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-orange-950/35 dark:text-orange-200 dark:ring-orange-400/45',
            'pending_face' => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/35 dark:text-blue-200 dark:ring-blue-400/45',
            'pending_selfie' => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/35 dark:text-blue-200 dark:ring-blue-400/45',
            'pending_checklist' => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/35 dark:text-blue-200 dark:ring-blue-400/45',
            'submitted' => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/35 dark:text-blue-200 dark:ring-blue-400/45',
            'under_review' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/35 dark:text-amber-200 dark:ring-amber-400/45',
            'invalid' => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/35 dark:text-red-200 dark:ring-red-400/45',
            'failed' => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/35 dark:text-red-200 dark:ring-red-400/45',
            'not_required' => 'bg-slate-50 text-slate-700 ring-slate-200 dark:bg-slate-950/50 dark:text-slate-200 dark:ring-slate-500/60',
            'expired' => 'bg-slate-50 text-slate-700 ring-slate-200 dark:bg-slate-950/50 dark:text-slate-200 dark:ring-slate-500/60',
        ];

        $priorityClasses = [
            'low' => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950/35 dark:text-sky-200 dark:ring-sky-400/45',
            'normal' => 'bg-slate-50 text-slate-700 ring-slate-200 dark:bg-slate-950/50 dark:text-slate-200 dark:ring-slate-500/60',
            'high' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/35 dark:text-amber-200 dark:ring-amber-400/45',
            'critical' => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/35 dark:text-red-200 dark:ring-red-400/45',
        ];

        $summaryCards = [
            ['label' => 'Valid Patrols', 'value' => $summary['valid'], 'labelClass' => 'text-emerald-700 dark:text-emerald-300', 'valueClass' => 'text-emerald-900 dark:text-emerald-100'],
            ['label' => 'Suspicious', 'value' => $summary['suspicious'], 'labelClass' => 'text-amber-700 dark:text-amber-300', 'valueClass' => 'text-amber-900 dark:text-amber-100'],
            ['label' => 'Invalid Scans', 'value' => $summary['invalid'], 'labelClass' => 'text-red-700 dark:text-red-300', 'valueClass' => 'text-red-900 dark:text-red-100'],
            ['label' => 'Pending Selfie', 'value' => $summary['pendingSelfie'], 'labelClass' => 'text-sky-700 dark:text-sky-300', 'valueClass' => 'text-sky-900 dark:text-sky-100'],
            ['label' => 'Pending Checklist', 'value' => $summary['pendingChecklist'], 'labelClass' => 'text-blue-700 dark:text-blue-300', 'valueClass' => 'text-blue-950 dark:text-blue-100'],
            ['label' => 'Outside Schedule', 'value' => $summary['outsideSchedule'], 'labelClass' => 'text-orange-700 dark:text-orange-300', 'valueClass' => 'text-orange-900 dark:text-orange-100'],
            ['label' => 'Incidents', 'value' => $summary['incidents'], 'labelClass' => 'text-blue-700 dark:text-blue-300', 'valueClass' => 'text-blue-950 dark:text-blue-100'],
        ];
    @endphp

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form
                method="GET"
                action="{{ route('reports.index') }}"
                x-data="{
                    from: @js($from->toDateString()),
                    to: @js($to->toDateString()),
                    syncToDate() {
                        if (this.from && (! this.to || this.to < this.from)) {
                            this.to = this.from;
                        }
                    },
                }"
                x-init="syncToDate()"
                x-on:submit="syncToDate()"
                class="grid gap-4 rounded-md border border-blue-100 bg-white p-4 shadow-sm md:grid-cols-[1fr_1fr_auto] print:hidden"
            >
                <div>
                    <label for="from" class="block text-xs font-semibold uppercase text-blue-800">From</label>
                    <input id="from" name="from" type="date" value="{{ $from->toDateString() }}" x-model="from" x-on:change="syncToDate()" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="to" class="block text-xs font-semibold uppercase text-blue-800">To</label>
                    <input id="to" name="to" type="date" value="{{ $to->toDateString() }}" x-model="to" x-bind:min="from || null" x-on:change="syncToDate()" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
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

                <div class="mt-6 grid grid-cols-2 gap-3 lg:grid-cols-7">
                    @foreach ($summaryCards as $card)
                        <div class="min-h-[5.5rem] rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-4 dark:border-slate-700 dark:bg-slate-900">
                            <p class="min-h-8 text-[0.68rem] font-bold uppercase leading-4 tracking-wide sm:text-[0.72rem] {{ $card['labelClass'] }}">{{ $card['label'] }}</p>
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
                                    {{ $patrol->status === 'pending_face' ? 'Pending Selfie' : str($patrol->status)->replace('_', ' ')->title() }}
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
                            </div>
                        </article>
                    @empty
                        <div class="rounded-md border border-blue-100 px-5 py-8 text-center text-slate-500">No patrol records for this period.</div>
                    @endforelse
                </div>

                <div class="hidden overflow-x-auto lg:block print:block">
                    <table class="w-full min-w-[56rem] divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-extrabold uppercase text-blue-800">
                            <tr>
                                <th class="whitespace-nowrap px-5 py-3">Date/Time</th>
                                <th class="whitespace-nowrap px-5 py-3">Guard</th>
                                <th class="whitespace-nowrap px-5 py-3">Checkpoint</th>
                                <th class="whitespace-nowrap px-5 py-3">RFID</th>
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
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$patrol->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ $patrol->status === 'pending_face' ? 'Pending Selfie' : str($patrol->status)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-slate-500">No patrol records for this period.</td>
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
                    <table class="w-full min-w-[64rem] divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-extrabold uppercase text-blue-800">
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
