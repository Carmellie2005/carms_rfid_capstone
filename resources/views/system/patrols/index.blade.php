<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">{{ $isSupervisor ? __('Patrol Logs') : __('My Patrol Logs') }}</h2>
                <p class="mt-1 text-sm text-blue-600">{{ \App\Support\FaceVerification::enabled() ? 'RFID scans, facial verification results, and checklist records' : 'RFID scans, checklist records, and incident reports' }}</p>
            </div>
            @unless ($isSupervisor)
                <a href="{{ route('patrol.scan') }}" class="inline-flex w-full items-center justify-center rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto">
                    New Scan
                </a>
            @endunless
        </div>
    </x-slot>

    @php
        $faceVerificationEnabled = \App\Support\FaceVerification::enabled();
        $statusClasses = [
            'valid' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            'suspicious' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'invalid' => 'bg-red-50 text-red-700 ring-red-200',
            'pending_face' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'pending_checklist' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'profile_incomplete' => 'bg-violet-50 text-violet-700 ring-violet-200',
            'outside_schedule' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'expired' => 'bg-slate-50 text-slate-700 ring-slate-200',
            'not_required' => 'bg-slate-50 text-slate-700 ring-slate-200',
        ];
    @endphp

    <div class="py-5 sm:py-8" x-data="{
        detailsOpen: false,
        selectedPatrolId: '',
        proofPhotoOpen: false,
        proofPhotoSrc: '',
        proofPhotoTitle: '',
        openPatrolDetails(id) {
            this.selectedPatrolId = String(id || '');
            this.detailsOpen = Boolean(this.selectedPatrolId);
        },
        closePatrolDetails() {
            this.detailsOpen = false;
            this.selectedPatrolId = '';
        },
        openProofPhoto(src, title) {
            this.proofPhotoSrc = src;
            this.proofPhotoTitle = title || 'Checklist proof photo';
            this.proofPhotoOpen = true;
        },
        closeProofPhoto() {
            this.proofPhotoOpen = false;
            this.proofPhotoSrc = '';
            this.proofPhotoTitle = '';
        },
    }" x-on:keydown.escape.window="if (proofPhotoOpen) { closeProofPhoto() } else if (detailsOpen) { closePatrolDetails() }">
        <div class="mx-auto max-w-[96rem] space-y-5 px-4 sm:px-6 lg:px-8">
            @php
                $exportQuery = request()->only(['status', 'guard_id', 'checkpoint_id', 'date']);
            @endphp

            <form method="GET" action="{{ route('patrol-logs.index') }}" class="grid gap-3 rounded-md border border-blue-100 bg-white p-3 shadow-sm {{ $isSupervisor ? 'md:grid-cols-2 xl:grid-cols-[minmax(150px,0.8fr)_minmax(170px,1fr)_minmax(150px,0.8fr)_minmax(140px,0.75fr)_auto]' : 'md:grid-cols-2 xl:grid-cols-[minmax(150px,0.8fr)_minmax(150px,0.8fr)_minmax(140px,0.75fr)_auto]' }}">
                <div>
                    <label for="status" class="block text-xs font-semibold uppercase text-blue-800">Status</label>
                    <select id="status" name="status" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All</option>
                        @foreach (['valid' => 'Valid', 'suspicious' => 'Suspicious', 'invalid' => 'Invalid', 'pending_checklist' => 'Pending Checklist', 'pending_face' => 'Pending Face', 'profile_incomplete' => 'Profile Incomplete', 'outside_schedule' => 'Outside Schedule', 'expired' => 'Expired'] as $value => $label)
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
                    <button class="h-9 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50" type="submit">Filter</button>
                    <a href="{{ route('patrol-logs.index') }}" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">Clear</a>
                    <a href="{{ route('patrol-logs.pdf', $exportQuery) }}" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                        Download PDF
                    </a>
                    <a href="{{ route('patrol-logs.pdf', array_merge($exportQuery, ['print' => 1])) }}" target="_blank" rel="noopener" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                        Print PDF
                    </a>
                </div>
            </form>

            <div class="grid gap-5 transition-all duration-300" :class="detailsOpen ? 'lg:grid-cols-[minmax(0,1fr)_27rem]' : 'lg:grid-cols-[minmax(0,1fr)]'">
                <div class="min-w-0 space-y-5">

            <div class="flex flex-col gap-3 rounded-md border border-blue-100 bg-white px-4 py-3 text-sm text-slate-600 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                <p class="whitespace-nowrap">
                    @if ($logs->total())
                        <span class="font-semibold text-blue-950">Showing {{ $logs->firstItem() }} to {{ $logs->lastItem() }} of {{ $logs->total() }} {{ $isSupervisor ? 'patrol logs' : 'my patrol logs' }}</span>
                    @else
                        No patrol logs to display
                    @endif
                </p>

                @if ($logs->hasPages())
                    <div class="flex items-center gap-2">
                        @if ($logs->onFirstPage())
                            <span class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 px-3 text-xs font-semibold text-slate-400">Previous</span>
                        @else
                            <a href="{{ $logs->previousPageUrl() }}" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">Previous</a>
                        @endif

                        <span class="whitespace-nowrap text-xs font-semibold text-slate-500">
                            Page {{ $logs->currentPage() }} of {{ $logs->lastPage() }}
                        </span>

                        @if ($logs->hasMorePages())
                            <a href="{{ $logs->nextPageUrl() }}" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">Next</a>
                        @else
                            <span class="inline-flex h-9 items-center justify-center rounded-md border border-slate-200 px-3 text-xs font-semibold text-slate-400">Next</span>
                        @endif
                    </div>
                @endif
            </div>

            <div class="grid gap-3 lg:hidden">
                @forelse ($logs as $log)
                    @php
                        $scanTime = $log->scanned_at?->timezone(config('app.timezone'));
                    @endphp
                    <article class="min-w-0 rounded-md border border-blue-100 bg-white p-3 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <p class="truncate text-[0.65rem] font-semibold uppercase text-blue-800 sm:text-xs">{{ $scanTime?->format('M d, Y') ?? 'No date' }}</p>
                                <p class="mt-1 text-sm font-semibold text-blue-950">{{ $scanTime?->format('h:i A') ?? 'No time' }}</p>
                            </div>
                            <span class="max-w-[7rem] shrink-0 truncate whitespace-nowrap rounded-md px-2 py-1 text-[0.65rem] font-semibold ring-1 sm:px-2.5 sm:text-xs {{ $statusClasses[$log->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                {{ str($log->status)->replace('_', ' ')->title() }}
                            </span>
                        </div>

                        <dl class="mt-3 grid gap-2 text-xs text-slate-600 sm:text-sm">
                            <div class="min-w-0">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800 sm:text-xs">Guard</dt>
                                <dd class="mt-1 truncate font-medium text-slate-900">{{ $log->securityGuard?->name ?? 'Unknown' }}</dd>
                                <dd class="truncate text-xs text-slate-500">{{ $log->securityGuard?->employee_no ?? 'No guard match' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800 sm:text-xs">Checkpoint</dt>
                                <dd class="mt-1 truncate font-medium text-slate-900">{{ $log->checkpoint?->name ?? 'Unknown' }}</dd>
                                <dd class="truncate font-mono text-xs text-slate-500">{{ $log->checkpoint_code }}</dd>
                            </div>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div class="min-w-0">
                                    <dt class="text-[0.65rem] font-semibold uppercase text-blue-800 sm:text-xs">RFID</dt>
                                    <dd class="mt-1 truncate font-mono">{{ $log->rfid_uid }}</dd>
                                </div>
                                @if ($faceVerificationEnabled)
                                <div class="min-w-0">
                                    <dt class="text-[0.65rem] font-semibold uppercase text-blue-800 sm:text-xs">Face</dt>
                                    <dd class="mt-1 truncate">{{ str($log->facial_status)->replace('_', ' ')->title() }}</dd>
                                </div>
                                @endif
                            </div>
                        </dl>

                        <div class="mt-3 grid gap-2 rounded-md bg-slate-50 p-3 text-xs text-slate-600 sm:grid-cols-2 sm:text-sm">
                            <div class="min-w-0">
                                <p class="text-[0.65rem] font-bold uppercase tracking-wide text-blue-800 sm:text-xs">Checklist</p>
                                <p class="mt-1 font-semibold text-slate-800">{{ $log->checklistSummary() }}</p>
                                @if ($log->checklistPhotoCount() > 0)
                                    <p class="mt-1 text-xs text-slate-500">{{ $log->checklistPhotoCount() }} proof {{ str('photo')->plural($log->checklistPhotoCount()) }}</p>
                                @endif
                            </div>
                            <div class="min-w-0">
                                <p class="text-[0.65rem] font-bold uppercase tracking-wide text-blue-800 sm:text-xs">Incident</p>
                                @if ($log->incidentReport)
                                    <p class="mt-1 truncate font-semibold text-slate-800">{{ $log->incidentReport->category }}</p>
                                    <p class="text-xs text-slate-500">{{ str($log->incidentReport->status)->replace('_', ' ')->title() }}</p>
                                @else
                                    <p class="mt-1 font-semibold text-slate-500">None</p>
                                @endif
                            </div>
                        </div>

                        <div class="mt-3 flex justify-end">
                            <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-blue-200 bg-white px-3 text-xs font-bold text-blue-700 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="openPatrolDetails(@js((string) $log->id))" aria-label="View patrol details for {{ $log->securityGuard?->name ?? 'this patrol log' }}">
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" />
                                </svg>
                                View details
                            </button>
                        </div>
                    </article>
                @empty
                    <div class="rounded-md border border-blue-100 bg-white px-5 py-8 text-center text-slate-500 shadow-sm">No patrol logs found.</div>
                @endforelse
            </div>

            <div class="hidden overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm lg:block">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[76rem] divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-extrabold uppercase text-blue-800">
                            <tr>
                                <th class="px-5 py-3">Time</th>
                                <th class="px-5 py-3">Guard</th>
                                <th class="px-5 py-3">Checkpoint</th>
                                <th class="px-5 py-3">RFID</th>
                                @if ($faceVerificationEnabled)
                                    <th class="px-5 py-3">Face</th>
                                @endif
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3">Checklist</th>
                                <th class="px-5 py-3">Incident</th>
                                <th class="px-5 py-3 text-center">View</th>
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
                                    @if ($faceVerificationEnabled)
                                        <td class="px-5 py-4 text-slate-600">{{ str($log->facial_status)->replace('_', ' ')->title() }}</td>
                                    @endif
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$log->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ str($log->status)->replace('_', ' ')->title() }}
                                        </span>
                                        @if ($log->notes)
                                            <div class="mt-2 max-w-xs text-xs text-slate-500">{{ $log->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-slate-600">
                                        <div class="font-semibold text-slate-800">{{ $log->checklistSummary() }}</div>
                                        @if ($log->checklistPhotoCount() > 0)
                                            <div class="mt-1 text-xs text-slate-500">{{ $log->checklistPhotoCount() }} proof {{ str('photo')->plural($log->checklistPhotoCount()) }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-slate-600">
                                        @if ($log->incidentReport)
                                            <span class="font-medium text-slate-900">{{ $log->incidentReport->category }}</span>
                                            <div class="text-xs">{{ str($log->incidentReport->status)->replace('_', ' ')->title() }}</div>
                                        @else
                                            <span class="text-xs text-slate-500">None</span>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-center">
                                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-blue-200 bg-white text-blue-700 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="openPatrolDetails(@js((string) $log->id))" aria-label="View patrol details for {{ $log->securityGuard?->name ?? 'this patrol log' }}" title="View patrol details">
                                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" />
                                            </svg>
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $faceVerificationEnabled ? 9 : 8 }}" class="px-5 py-8 text-center text-slate-500">No patrol logs found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-md border border-blue-100 bg-white px-5 py-4 shadow-sm">
                {{ $logs->links() }}
            </div>

                </div>

                <div x-show="detailsOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[70] bg-slate-950/45 lg:hidden" @click="closePatrolDetails()"></div>

                <aside x-show="detailsOpen" x-cloak x-transition class="fixed inset-x-0 bottom-0 z-[80] max-h-[88dvh] overflow-hidden rounded-t-md border border-blue-100 bg-white shadow-2xl lg:sticky lg:inset-auto lg:top-24 lg:z-auto lg:max-h-[calc(100dvh-8rem)] lg:self-start lg:rounded-md" aria-label="Patrol details panel">
                    @foreach ($logs as $log)
                        @php
                            $detailScanTime = $log->scanned_at?->timezone(config('app.timezone'));
                            $detailChecklistItems = \App\Support\PatrolChecklist::statusSummaries($log->checklistResponse);
                            $detailProofPhotos = $log->checklistResponse?->proofPhotos ?? collect();
                        @endphp

                        <section x-show="selectedPatrolId === @js((string) $log->id)" x-cloak class="flex max-h-[88dvh] flex-col overflow-hidden lg:max-h-[calc(100dvh-8rem)]">
                            <div class="flex items-start justify-between gap-3 border-b border-blue-100 px-5 py-4">
                                <div class="min-w-0">
                                    <p class="text-xs font-bold uppercase tracking-wide text-blue-700">Patrol Record</p>
                                    <h3 class="mt-1 text-lg font-bold text-blue-950">Patrol Details</h3>
                                    <p class="mt-1 truncate text-sm text-slate-500">{{ $log->securityGuard?->name ?? 'Unknown guard' }} - {{ $log->checkpoint?->name ?? 'Unknown checkpoint' }}</p>
                                </div>
                                <button type="button" class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-blue-100 bg-white text-slate-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="closePatrolDetails()" aria-label="Close patrol details">
                                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                    </svg>
                                </button>
                            </div>

                            <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
                                <dl class="grid gap-3 text-sm">
                                    <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3">
                                        <dt class="font-bold text-slate-600">Guard</dt>
                                        <dd class="min-w-0">
                                            <p class="truncate font-semibold text-blue-950">{{ $log->securityGuard?->name ?? 'Unknown' }}</p>
                                            <p class="truncate text-xs text-slate-500">{{ $log->securityGuard?->employee_no ?? 'No guard match' }}</p>
                                        </dd>
                                    </div>
                                    <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3">
                                        <dt class="font-bold text-slate-600">Checkpoint</dt>
                                        <dd class="min-w-0">
                                            <p class="truncate font-semibold text-blue-950">{{ $log->checkpoint?->name ?? 'Unknown' }}</p>
                                            <p class="truncate font-mono text-xs text-slate-500">{{ $log->checkpoint_code }}</p>
                                        </dd>
                                    </div>
                                    <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3">
                                        <dt class="font-bold text-slate-600">Time</dt>
                                        <dd class="text-slate-700">{{ $detailScanTime?->format('M d, Y h:i A') ?? 'No scan time' }}</dd>
                                    </div>
                                    <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3">
                                        <dt class="font-bold text-slate-600">RFID</dt>
                                        <dd class="truncate font-mono text-slate-700">{{ $log->rfid_uid }}</dd>
                                    </div>
                                    @if ($faceVerificationEnabled)
                                        <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3">
                                            <dt class="font-bold text-slate-600">Face</dt>
                                            <dd class="text-slate-700">{{ str($log->facial_status)->replace('_', ' ')->title() }}</dd>
                                        </div>
                                    @endif
                                    <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3">
                                        <dt class="font-bold text-slate-600">Status</dt>
                                        <dd>
                                            <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-bold ring-1 {{ $statusClasses[$log->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                                {{ str($log->status)->replace('_', ' ')->title() }}
                                            </span>
                                        </dd>
                                    </div>
                                </dl>

                                @if ($log->notes)
                                    <div class="mt-4 rounded-md border border-blue-100 bg-blue-50/60 p-3 text-sm text-blue-900">
                                        <p class="font-bold">Note</p>
                                        <p class="mt-1 text-blue-800">{{ $log->notes }}</p>
                                    </div>
                                @endif

                                <div class="mt-5 border-t border-blue-100 pt-4">
                                    <h4 class="text-base font-bold text-blue-950">Checklist</h4>
                                    @if ($log->checklistResponse)
                                        <div class="mt-3 grid gap-2">
                                            @forelse ($detailChecklistItems as $item)
                                                <div class="flex items-start justify-between gap-3 rounded-md border border-blue-100 bg-white px-3 py-2">
                                                    <p class="min-w-0 text-sm font-medium text-slate-700">{{ $item['label'] }}</p>
                                                    <span class="shrink-0 whitespace-nowrap rounded-md px-2 py-1 text-xs font-bold ring-1 {{ \App\Support\PatrolChecklist::statusBadgeClasses($item['status']) }}">{{ $item['status_label'] }}</span>
                                                </div>
                                            @empty
                                                <p class="rounded-md border border-blue-100 bg-slate-50 px-3 py-2 text-sm text-slate-500">No checklist status recorded.</p>
                                            @endforelse
                                        </div>

                                        @if ($log->checklistResponse->remarks)
                                            <div class="mt-3 rounded-md border border-blue-100 bg-slate-50 px-3 py-2 text-sm text-slate-600">
                                                <p class="font-bold text-slate-700">Remarks</p>
                                                <p class="mt-1">{{ $log->checklistResponse->remarks }}</p>
                                            </div>
                                        @endif
                                    @else
                                        <p class="mt-3 rounded-md border border-blue-100 bg-slate-50 px-3 py-2 text-sm text-slate-500">No checklist submitted yet.</p>
                                    @endif
                                </div>

                                <div class="mt-5 border-t border-blue-100 pt-4">
                                    <h4 class="text-base font-bold text-blue-950">Proof Photos ({{ $detailProofPhotos->count() }})</h4>
                                    @if ($detailProofPhotos->isNotEmpty())
                                        <div class="mt-3 grid grid-cols-3 gap-2">
                                            @foreach ($detailProofPhotos as $photo)
                                                @php
                                                    $photoUrl = route('patrol-logs.proof-photos.show', [$log, $photo]);
                                                @endphp
                                                <button type="button" class="group h-24 overflow-hidden rounded-md border border-blue-100 bg-slate-100 shadow-sm transition hover:border-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="openProofPhoto(@js($photoUrl), @js($photo->item_label))" aria-label="Open {{ $photo->item_label }} proof photo">
                                                    <img :src="selectedPatrolId === @js((string) $log->id) ? @js($photoUrl) : ''" alt="{{ $photo->item_label }} proof thumbnail" class="h-full w-full object-cover transition group-hover:scale-105" loading="lazy">
                                                </button>
                                            @endforeach
                                        </div>
                                    @else
                                        <p class="mt-3 rounded-md border border-blue-100 bg-slate-50 px-3 py-2 text-sm text-slate-500">No proof photos uploaded.</p>
                                    @endif
                                </div>

                                <div class="mt-5 border-t border-blue-100 pt-4">
                                    <h4 class="text-base font-bold text-blue-950">Incident</h4>
                                    @if ($log->incidentReport)
                                        <div class="mt-3 rounded-md border border-amber-100 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                                            <p class="font-bold">{{ $log->incidentReport->category }}</p>
                                            <p class="mt-1">Status: {{ str($log->incidentReport->status)->replace('_', ' ')->title() }}</p>
                                            <a href="{{ route('incidents.pdf', $log->incidentReport) }}" class="mt-3 inline-flex h-9 items-center justify-center rounded-md border border-amber-200 bg-white px-3 text-xs font-bold text-amber-800 transition hover:bg-amber-100">
                                                Download Incident PDF
                                            </a>
                                        </div>
                                    @else
                                        <p class="mt-3 rounded-md border border-blue-100 bg-slate-50 px-3 py-2 text-sm text-slate-500">No incident report attached.</p>
                                    @endif
                                </div>
                            </div>

                            <div class="border-t border-blue-100 bg-white px-5 py-3">
                                <button type="button" class="inline-flex h-10 w-full items-center justify-center rounded-md border border-blue-200 bg-white px-4 text-sm font-bold text-blue-700 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="closePatrolDetails()">
                                    Close
                                </button>
                            </div>
                        </section>
                    @endforeach
                </aside>
            </div>
        </div>

        <div x-show="proofPhotoOpen" x-cloak x-transition.opacity.duration.200ms class="fixed inset-0 z-[95] flex items-center justify-center bg-slate-950/70 p-3 sm:p-6" x-on:click.self="closeProofPhoto()" x-on:keydown.escape.window="proofPhotoOpen && closeProofPhoto()">
            <section class="w-full max-w-2xl overflow-hidden rounded-md bg-white shadow-2xl">
                <div class="flex items-center justify-between gap-3 border-b border-blue-100 px-4 py-3">
                    <p class="min-w-0 truncate text-sm font-semibold text-blue-950" x-text="proofPhotoTitle"></p>
                    <button type="button" class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-blue-100 bg-white text-slate-700 hover:bg-blue-50" @click="closeProofPhoto()" aria-label="Close proof photo preview">
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>
                <div class="bg-slate-950 p-2 sm:p-3">
                    <img x-show="proofPhotoSrc" :src="proofPhotoSrc" alt="Checklist proof photo" class="max-h-[78dvh] w-full rounded object-contain">
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
