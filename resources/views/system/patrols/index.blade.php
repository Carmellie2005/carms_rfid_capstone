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

    <div class="py-5 sm:py-8" x-data="{
        proofPhotoOpen: false,
        proofPhotoSrc: '',
        proofPhotoTitle: '',
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
    }">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @php
                $exportQuery = request()->only(['status', 'guard_id', 'checkpoint_id', 'date']);
            @endphp

            <form method="GET" action="{{ route('patrol-logs.index') }}" class="grid gap-3 rounded-md border border-blue-100 bg-white p-3 shadow-sm {{ $isSupervisor ? 'md:grid-cols-2 xl:grid-cols-[minmax(150px,0.8fr)_minmax(170px,1fr)_minmax(150px,0.8fr)_minmax(140px,0.75fr)_auto]' : 'md:grid-cols-2 xl:grid-cols-[minmax(150px,0.8fr)_minmax(150px,0.8fr)_minmax(140px,0.75fr)_auto]' }}">
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
                                <div class="min-w-0">
                                    <dt class="text-[0.65rem] font-semibold uppercase text-blue-800 sm:text-xs">Face</dt>
                                    <dd class="mt-1 truncate">{{ str($log->facial_status)->replace('_', ' ')->title() }}</dd>
                                </div>
                            </div>
                        </dl>

                        @if ($log->checklistResponse)
                            @php
                                $mobileFlags = \App\Support\PatrolChecklist::statusSummaries($log->checklistResponse);
                                $mobileProofPhotos = $log->checklistResponse->proofPhotos;
                            @endphp
                            <div class="mt-3 flex flex-wrap gap-1">
                                @forelse ($mobileFlags as $item)
                                    <span class="rounded-md px-2 py-1 text-[0.65rem] ring-1 sm:text-xs {{ \App\Support\PatrolChecklist::statusBadgeClasses($item['status']) }}">{{ $item['label'] }}: {{ $item['status_label'] }}</span>
                                @empty
                                    <span class="text-xs text-slate-500">No checklist status recorded</span>
                                @endforelse
                            </div>
                            @if ($mobileProofPhotos->isNotEmpty())
                                <div class="mt-3">
                                    <p class="text-[0.65rem] font-semibold uppercase text-blue-800 sm:text-xs">Proof Photos</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        @foreach ($mobileProofPhotos as $photo)
                                            @php
                                                $photoUrl = route('patrol-logs.proof-photos.show', [$log, $photo]);
                                            @endphp
                                            <button type="button" class="h-14 w-14 overflow-hidden rounded-md border border-blue-100 bg-slate-100 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="openProofPhoto(@js($photoUrl), @js($photo->item_label))" aria-label="Open {{ $photo->item_label }} proof photo">
                                                <img src="{{ $photoUrl }}" alt="{{ $photo->item_label }} proof thumbnail" class="h-full w-full object-cover" loading="lazy">
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        @endif

                        <div class="mt-3 rounded-md bg-slate-50 p-2 text-xs text-slate-600 sm:p-3 sm:text-sm">
                            <span class="font-semibold text-slate-800">Incident:</span>
                            @if ($log->incidentReport)
                                {{ $log->incidentReport->category }} - {{ str($log->incidentReport->status)->replace('_', ' ')->title() }}
                                <a href="{{ route('incidents.pdf', $log->incidentReport) }}" class="mt-2 inline-flex items-center justify-center whitespace-nowrap rounded-md border border-blue-200 px-2.5 py-1.5 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                                    Download PDF
                                </a>
                            @else
                                None
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="rounded-md border border-blue-100 bg-white px-5 py-8 text-center text-slate-500 shadow-sm">No patrol logs found.</div>
                @endforelse
            </div>

            <div class="hidden overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm lg:block">
                <div class="overflow-x-auto">
                    <table class="min-w-[72rem] divide-y divide-blue-100 text-sm">
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
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$log->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ str($log->status)->replace('_', ' ')->title() }}
                                        </span>
                                        @if ($log->notes)
                                            <div class="mt-2 max-w-xs text-xs text-slate-500">{{ $log->notes }}</div>
                                        @endif
                                    </td>
                                    <td class="px-5 py-4 text-slate-600">
                                        @if ($log->checklistResponse)
                                            @php
                                                $flags = \App\Support\PatrolChecklist::statusSummaries($log->checklistResponse);
                                                $proofPhotos = $log->checklistResponse->proofPhotos;
                                            @endphp
                                            <div class="flex max-w-xs flex-wrap gap-1">
                                                @forelse ($flags as $item)
                                                    <span class="rounded px-2 py-1 text-xs ring-1 {{ \App\Support\PatrolChecklist::statusBadgeClasses($item['status']) }}">{{ $item['label'] }}: {{ $item['status_label'] }}</span>
                                                @empty
                                                    <span class="text-xs text-slate-500">No checklist status recorded</span>
                                                @endforelse
                                            </div>
                                            @if ($proofPhotos->isNotEmpty())
                                                <div class="mt-3 flex max-w-xs flex-wrap gap-2">
                                                    @foreach ($proofPhotos as $photo)
                                                        @php
                                                            $photoUrl = route('patrol-logs.proof-photos.show', [$log, $photo]);
                                                        @endphp
                                                        <button type="button" class="h-12 w-12 overflow-hidden rounded-md border border-blue-100 bg-slate-100 shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2" @click="openProofPhoto(@js($photoUrl), @js($photo->item_label))" aria-label="Open {{ $photo->item_label }} proof photo">
                                                            <img src="{{ $photoUrl }}" alt="{{ $photo->item_label }} proof thumbnail" class="h-full w-full object-cover" loading="lazy">
                                                        </button>
                                                    @endforeach
                                                </div>
                                            @endif
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

            <div class="rounded-md border border-blue-100 bg-white px-5 py-4 shadow-sm">
                {{ $logs->links() }}
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
