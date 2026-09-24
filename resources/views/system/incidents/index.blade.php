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
            'low' => 'bg-sky-50 text-sky-700 ring-sky-200 dark:bg-sky-950/35 dark:text-sky-200 dark:ring-sky-400/45',
            'normal' => 'bg-slate-50 text-slate-700 ring-slate-200 dark:bg-slate-950/50 dark:text-slate-200 dark:ring-slate-500/60',
            'high' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/35 dark:text-amber-200 dark:ring-amber-400/45',
            'critical' => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/35 dark:text-red-200 dark:ring-red-400/45',
        ];

        $statusClasses = [
            'submitted' => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/35 dark:text-blue-200 dark:ring-blue-400/45',
            'under_review' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/35 dark:text-amber-200 dark:ring-amber-400/45',
            'resolved' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/35 dark:text-emerald-200 dark:ring-emerald-400/45',
        ];
    @endphp

    <div
        class="py-5 sm:py-8"
        x-data="{
            pdfPreviewOpen: false,
            pdfPreviewUrl: '',
            pdfDownloadUrl: '',
            pdfPreviewTitle: 'Incident PDF Preview',
            openIncidentPdfPreview(previewUrl, downloadUrl, title) {
                this.pdfPreviewUrl = previewUrl;
                this.pdfDownloadUrl = downloadUrl;
                this.pdfPreviewTitle = title || 'Incident PDF Preview';
                this.pdfPreviewOpen = true;
                document.body.classList.add('overflow-y-hidden');
                this.$nextTick(() => this.$refs.incidentPdfCloseButton?.focus());
            },
            closeIncidentPdfPreview() {
                this.pdfPreviewOpen = false;
                this.pdfPreviewUrl = '';
                this.pdfDownloadUrl = '';
                document.body.classList.remove('overflow-y-hidden');
            },
        }"
        x-on:keydown.escape.window="pdfPreviewOpen && closeIncidentPdfPreview()"
    >
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
                        $incidentPdfDownloadUrl = route('incidents.pdf', $incident);
                        $incidentPdfPreviewUrl = route('incidents.pdf', ['incidentReport' => $incident, 'preview' => 1]);
                        $incidentPdfTitle = 'Incident Report - '.($incident->category ?: 'Incident');
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
                                    <div class="flex shrink-0 flex-wrap items-center gap-2">
                                        <button
                                            type="button"
                                            data-skip-global-loader="true"
                                            x-on:click="openIncidentPdfPreview(@js($incidentPdfPreviewUrl), @js($incidentPdfDownloadUrl), @js($incidentPdfTitle))"
                                            class="inline-flex items-center justify-center whitespace-nowrap rounded-md border border-blue-200 px-3 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:text-blue-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950"
                                        >
                                            Print PDF
                                        </button>
                                        <a href="{{ $incidentPdfDownloadUrl }}" class="inline-flex items-center justify-center whitespace-nowrap rounded-md border border-blue-200 px-3 py-2 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:text-blue-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950">
                                            Download PDF
                                        </a>
                                    </div>
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
                                @if ($incident->action_taken)
                                    <div class="mt-3 rounded-md bg-emerald-50 p-3 text-sm text-emerald-900">
                                        <span class="font-semibold">Action taken:</span> {{ $incident->action_taken }}
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
                                    <label for="admin_notes-{{ $incident->id }}" class="sr-only">Admin Notes</label>
                                    <textarea id="admin_notes-{{ $incident->id }}" name="admin_notes" rows="4" placeholder="Admin Notes" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('admin_notes', $incident->admin_notes) }}</textarea>
                                </div>
                                <div class="mt-3">
                                    <label for="action_taken-{{ $incident->id }}" class="block text-sm font-medium text-slate-700">Action Taken</label>
                                    <textarea id="action_taken-{{ $incident->id }}" name="action_taken" rows="4" placeholder="Required when marking the report as resolved" class="mt-1 block w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('action_taken', $incident->action_taken) }}</textarea>
                                    <p class="mt-1 text-xs text-slate-500">Use this to record what was done, such as repairs, inspection, referral, or security response.</p>
                                    <x-input-error :messages="$errors->get('action_taken')" class="mt-2" />
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

        <div
            x-show="pdfPreviewOpen"
            x-cloak
            x-transition.opacity.duration.150ms
            class="fixed inset-0 z-[80] bg-slate-950/60"
            x-on:click="closeIncidentPdfPreview()"
            aria-hidden="true"
        ></div>

        <section
            x-show="pdfPreviewOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 translate-y-3 scale-[0.98]"
            x-transition:enter-end="opacity-100 translate-y-0 scale-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 translate-y-0 scale-100"
            x-transition:leave-end="opacity-0 translate-y-3 scale-[0.98]"
            class="fixed inset-0 z-[85] flex items-center justify-center p-3 sm:p-6"
            role="dialog"
            aria-modal="true"
            aria-labelledby="incident-pdf-preview-title"
        >
            <div class="flex h-[min(92dvh,56rem)] w-full max-w-5xl flex-col overflow-hidden rounded-md border border-blue-100 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900" x-on:click.stop>
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-blue-100 px-4 py-3 dark:border-slate-800 sm:px-5">
                    <div class="min-w-0">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">PDF Preview</p>
                        <h3 id="incident-pdf-preview-title" class="mt-1 truncate text-base font-semibold text-blue-950 dark:text-blue-100" x-text="pdfPreviewTitle"></h3>
                    </div>
                    <button
                        type="button"
                        x-ref="incidentPdfCloseButton"
                        x-on:click="closeIncidentPdfPreview()"
                        class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-blue-100 text-slate-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900"
                        aria-label="Close PDF preview"
                    >
                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                    </button>
                </div>

                <div class="min-h-0 flex-1 bg-slate-100 dark:bg-slate-950">
                    <iframe
                        x-bind:src="pdfPreviewOpen ? pdfPreviewUrl : 'about:blank'"
                        title="Incident report PDF preview"
                        class="h-full w-full border-0 bg-white"
                    ></iframe>
                </div>

                <div class="flex shrink-0 flex-col gap-2 border-t border-blue-100 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-end sm:px-5">
                    <a
                        x-bind:href="pdfPreviewUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex h-10 items-center justify-center rounded-md border border-blue-200 px-4 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:text-blue-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900"
                    >
                        Open / Print
                    </a>
                    <a
                        x-bind:href="pdfDownloadUrl"
                        class="inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                    >
                        Download PDF
                    </a>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
