<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">{{ __('Incident Reports') }}</h2>
                <p class="mt-1 text-sm text-blue-600">Submitted security concerns and administrator review</p>
            </div>
        </div>
    </x-slot>

    @php
        $priorityClasses = [
            'low' => 'bg-sky-50 text-sky-700 ring-sky-200',
            'normal' => 'bg-slate-50 text-slate-700 ring-slate-200',
            'high' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'critical' => 'bg-red-50 text-red-700 ring-red-200',
        ];

        $statusClasses = [
            'submitted' => 'bg-blue-50 text-blue-700 ring-blue-200',
            'under_review' => 'bg-amber-50 text-amber-700 ring-amber-200',
            'resolved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
        ];
    @endphp

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800">{{ session('status') }}</div>
            @endif

            <form method="GET" action="{{ route('incidents.index') }}" class="grid gap-4 rounded-md border border-blue-100 bg-white p-4 shadow-sm md:grid-cols-4">
                <div>
                    <label for="status" class="block text-xs font-semibold uppercase text-blue-800">Status</label>
                    <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All</option>
                        @foreach (['submitted' => 'Submitted', 'under_review' => 'Under Review', 'resolved' => 'Resolved'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="priority" class="block text-xs font-semibold uppercase text-blue-800">Priority</label>
                    <select id="priority" name="priority" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All</option>
                        @foreach (['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'critical' => 'Critical'] as $value => $label)
                            <option value="{{ $value }}" @selected(request('priority') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 items-end gap-2 md:col-span-2 md:flex">
                    <button class="h-10 rounded-md bg-blue-700 px-4 text-sm font-semibold text-white hover:bg-blue-800 md:w-auto" type="submit">Filter</button>
                    <a href="{{ route('incidents.index') }}" class="inline-flex h-10 items-center justify-center rounded-md border border-blue-200 px-4 text-sm font-semibold text-blue-700 hover:bg-blue-50">Clear</a>
                </div>
            </form>

            @if ($incidents->hasPages())
                <x-pagination-panel :paginator="$incidents" label="incident reports" page-label="Incident reports page" class="rounded-md border border-blue-100 bg-white px-4 py-3 shadow-sm" />
            @endif

            <div class="space-y-4">
                @forelse ($incidents as $incident)
                    @php
                        $incidentTime = $incident->incident_at?->timezone(config('app.timezone'));
                    @endphp
                    <article class="rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-5">
                        <div class="grid gap-5 lg:grid-cols-[1fr_360px]">
                            <div>
                                <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-lg font-semibold text-blue-950">{{ $incident->category }}</h3>
                                        <span class="whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $priorityClasses[$incident->priority] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ ucfirst($incident->priority) }}
                                        </span>
                                        <span class="whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $statusClasses[$incident->status] ?? 'bg-slate-50 text-slate-700 ring-slate-200' }}">
                                            {{ str($incident->status)->replace('_', ' ')->title() }}
                                        </span>
                                    </div>
                                    <a href="{{ route('incidents.pdf', $incident) }}" class="inline-flex shrink-0 items-center justify-center whitespace-nowrap rounded-md border border-blue-200 px-3 py-2 text-sm font-semibold text-blue-700 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                        Download PDF
                                    </a>
                                </div>
                                <dl class="mt-4 grid gap-3 text-sm text-slate-600 sm:grid-cols-3">
                                    <div>
                                        <dt class="font-semibold text-slate-800">Guard</dt>
                                        <dd>{{ $incident->securityGuard?->name ?? 'Unknown' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-800">Checkpoint</dt>
                                        <dd>{{ $incident->checkpoint?->name ?? 'Unassigned' }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-semibold text-slate-800">Date/Time</dt>
                                        <dd>{{ $incidentTime?->format('M d, Y h:i A') ?? 'Not recorded' }}</dd>
                                    </div>
                                </dl>
                                <p class="mt-4 whitespace-pre-line text-sm leading-6 text-slate-700">{{ $incident->description }}</p>
                                @php
                                    $incidentImages = $incident->images
                                        ->filter(fn ($image) => $image->image_data || ($image->image_path && \Illuminate\Support\Facades\Storage::disk('public')->exists($image->image_path)))
                                        ->values();
                                @endphp

                                @if ($incidentImages->isNotEmpty())
                                    <div class="mt-4 grid gap-2" style="grid-template-columns: repeat(auto-fit, minmax(9rem, 1fr));">
                                        @foreach ($incidentImages as $incidentImage)
                                            <a href="{{ route('incidents.images.show', [$incident, $incidentImage]) }}" target="_blank" class="block overflow-hidden rounded-md border border-blue-100 bg-blue-50">
                                                <img src="{{ route('incidents.images.show', [$incident, $incidentImage]) }}" alt="Incident image {{ $loop->iteration }}" class="h-36 w-full object-cover sm:h-44">
                                            </a>
                                        @endforeach
                                    </div>
                                @elseif ($incident->image_path || $incident->images->isNotEmpty())
                                    <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-800">
                                        Incident image is unavailable on this deployment.
                                    </div>
                                @endif
                                @if ($incident->admin_notes)
                                    <div class="mt-4 rounded-md bg-blue-50 p-3 text-sm text-blue-900">
                                        <span class="font-semibold">Admin notes:</span> {{ $incident->admin_notes }}
                                    </div>
                                @endif
                            </div>

                            <form method="POST" action="{{ route('incidents.update', $incident) }}" class="rounded-md border border-blue-100 p-4">
                                @csrf
                                @method('PATCH')
                                <div>
                                    <label for="status-{{ $incident->id }}" class="block text-sm font-medium text-slate-700">Review Status</label>
                                    <select id="status-{{ $incident->id }}" name="status" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        @foreach (['submitted' => 'Submitted', 'under_review' => 'Under Review', 'resolved' => 'Resolved'] as $value => $label)
                                            <option value="{{ $value }}" @selected($incident->status === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="mt-3">
                                    <label for="admin_notes-{{ $incident->id }}" class="block text-sm font-medium text-slate-700">Admin Notes</label>
                                    <textarea id="admin_notes-{{ $incident->id }}" name="admin_notes" rows="4" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('admin_notes', $incident->admin_notes) }}</textarea>
                                </div>
                                <button class="mt-4 w-full rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800" type="submit">Update Report</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="rounded-md border border-blue-100 bg-white px-5 py-8 text-center text-slate-500 shadow-sm">No incident reports found.</div>
                @endforelse
            </div>

            <x-pagination-panel :paginator="$incidents" label="incident reports" page-label="Incident reports page" class="rounded-md border border-blue-100 bg-white px-4 py-3 shadow-sm" />
        </div>
    </div>
</x-app-layout>
