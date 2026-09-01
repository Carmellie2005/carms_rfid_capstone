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

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
            <form method="GET" action="{{ route('reports.index') }}" class="grid gap-4 rounded-lg border border-blue-100 bg-white p-4 shadow-sm md:grid-cols-[1fr_1fr_auto] print:hidden">
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

            <section class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-6">
                <div class="flex items-start gap-4">
                    <x-application-logo class="h-14 w-14 shrink-0" />
                    <div>
                        <h3 class="text-lg font-semibold text-blue-950">SLSU Bontoc Patrol</h3>
                        <p class="mt-1 text-sm text-slate-600">Report period: {{ $from->format('M d, Y') }} to {{ $to->format('M d, Y') }}</p>
                    </div>
                </div>

                <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <div class="rounded-lg border border-blue-100 p-4">
                        <p class="text-sm font-medium text-slate-500">Valid Patrols</p>
                        <p class="mt-2 text-2xl font-semibold text-blue-950">{{ $summary['valid'] }}</p>
                    </div>
                    <div class="rounded-lg border border-blue-100 p-4">
                        <p class="text-sm font-medium text-slate-500">Suspicious</p>
                        <p class="mt-2 text-2xl font-semibold text-blue-950">{{ $summary['suspicious'] }}</p>
                    </div>
                    <div class="rounded-lg border border-blue-100 p-4">
                        <p class="text-sm font-medium text-slate-500">Invalid Scans</p>
                        <p class="mt-2 text-2xl font-semibold text-blue-950">{{ $summary['invalid'] }}</p>
                    </div>
                    <div class="rounded-lg border border-blue-100 p-4">
                        <p class="text-sm font-medium text-slate-500">Profile Incomplete</p>
                        <p class="mt-2 text-2xl font-semibold text-blue-950">{{ $summary['profileIncomplete'] }}</p>
                    </div>
                    <div class="rounded-lg border border-blue-100 p-4">
                        <p class="text-sm font-medium text-slate-500">Outside Schedule</p>
                        <p class="mt-2 text-2xl font-semibold text-blue-950">{{ $summary['outsideSchedule'] }}</p>
                    </div>
                    <div class="rounded-lg border border-blue-100 p-4">
                        <p class="text-sm font-medium text-slate-500">Incidents</p>
                        <p class="mt-2 text-2xl font-semibold text-blue-950">{{ $summary['incidents'] }}</p>
                    </div>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-blue-100 bg-white shadow-sm">
                <div class="border-b border-blue-100 px-5 py-4">
                    <h3 class="font-semibold text-blue-950">Patrol Summary</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-semibold uppercase text-blue-800">
                            <tr>
                                <th class="px-5 py-3">Date/Time</th>
                                <th class="px-5 py-3">Guard</th>
                                <th class="px-5 py-3">Checkpoint</th>
                                <th class="px-5 py-3">RFID</th>
                                <th class="px-5 py-3">Face</th>
                                <th class="px-5 py-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            @forelse ($patrols as $patrol)
                                @php
                                    $scanTime = $patrol->scanned_at?->timezone(config('app.timezone'));
                                @endphp
                                <tr>
                                    <td class="px-5 py-4 text-slate-600">{{ $scanTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</td>
                                    <td class="px-5 py-4 text-slate-900">{{ $patrol->securityGuard?->name ?? 'Unknown' }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $patrol->checkpoint?->name ?? $patrol->checkpoint_code }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ ucfirst($patrol->rfid_status) }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ str($patrol->facial_status)->replace('_', ' ')->title() }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ str($patrol->status)->replace('_', ' ')->title() }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-500">No patrol records for this period.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-blue-100 bg-white shadow-sm">
                <div class="border-b border-blue-100 px-5 py-4">
                    <h3 class="font-semibold text-blue-950">Incident Summary</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-semibold uppercase text-blue-800">
                            <tr>
                                <th class="px-5 py-3">Date/Time</th>
                                <th class="px-5 py-3">Category</th>
                                <th class="px-5 py-3">Location</th>
                                <th class="px-5 py-3">Priority</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Description</th>
                                <th class="px-5 py-3 print:hidden">PDF</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            @forelse ($incidents as $incident)
                                @php
                                    $incidentTime = $incident->incident_at?->timezone(config('app.timezone'));
                                @endphp
                                <tr class="align-top">
                                    <td class="px-5 py-4 whitespace-nowrap text-slate-600">{{ $incidentTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</td>
                                    <td class="px-5 py-4 font-medium text-slate-900">{{ $incident->category }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $incident->checkpoint?->name ?? 'Unassigned' }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ ucfirst($incident->priority) }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ str($incident->status)->replace('_', ' ')->title() }}</td>
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
            </section>
        </div>
    </div>
</x-app-layout>
