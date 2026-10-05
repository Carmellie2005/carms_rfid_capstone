<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-blue-950 dark:text-white">Audit Trail</h2>
            <p class="mt-1 text-sm text-blue-600 dark:text-blue-300">System activity and important security events</p>
        </div>
    </x-slot>

    <div
        class="py-5 sm:py-8"
        x-data="{
            pdfPreviewOpen: false,
            pdfPreviewUrl: '',
            pdfDownloadUrl: '',
            pdfPreviewTitle: 'Audit Trail PDF Preview',
            openAuditPdfPreview(previewUrl, downloadUrl, title) {
                this.pdfPreviewUrl = previewUrl;
                this.pdfDownloadUrl = downloadUrl;
                this.pdfPreviewTitle = title || 'Audit Trail PDF Preview';
                this.pdfPreviewOpen = true;
                document.body.classList.add('overflow-y-hidden');
                this.$nextTick(() => this.$refs.auditPdfCloseButton?.focus());
            },
            closeAuditPdfPreview() {
                this.pdfPreviewOpen = false;
                this.pdfPreviewUrl = '';
                this.pdfDownloadUrl = '';
                document.body.classList.remove('overflow-y-hidden');
            },
        }"
        x-on:keydown.escape.window="pdfPreviewOpen && closeAuditPdfPreview()"
    >
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @php
                $exportQuery = request()->only(['guard_id', 'action', 'date', 'search']);
                $auditPdfDownloadUrl = route('audit-logs.pdf', $exportQuery);
                $auditPdfPreviewUrl = route('audit-logs.pdf', array_merge($exportQuery, ['print' => 1]));
            @endphp

            <form method="GET" action="{{ route('audit-logs.index') }}" class="grid gap-3 rounded-md border border-blue-100 bg-white p-3 shadow-sm dark:border-slate-800 dark:bg-slate-900 md:grid-cols-2 xl:grid-cols-[minmax(170px,1fr)_minmax(170px,1fr)_minmax(140px,0.75fr)_minmax(220px,1.2fr)_auto] print:hidden">
                <div>
                    <label for="guard_id" class="block text-xs font-semibold uppercase text-blue-800 dark:text-blue-300">Guard</label>
                    <select id="guard_id" name="guard_id" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">All guards</option>
                        @foreach ($guards as $guard)
                            <option value="{{ $guard->id }}" @selected((string) request('guard_id') === (string) $guard->id)>{{ $guard->name }} - {{ $guard->employee_no }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="action" class="block text-xs font-semibold uppercase text-blue-800 dark:text-blue-300">Action</label>
                    <select id="action" name="action" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                        <option value="">All actions</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>{{ str($action)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date" class="block text-xs font-semibold uppercase text-blue-800 dark:text-blue-300">Date</label>
                    <input id="date" name="date" type="date" value="{{ request('date') }}" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                </div>
                <div>
                    <label for="search" class="sr-only">Search</label>
                    <input id="search" name="search" value="{{ request('search') }}" placeholder="Actor, action, or diagnostic" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder:text-slate-500">
                </div>
                <div class="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-1 xl:self-end xl:justify-end">
                    <button type="submit" class="h-9 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-200 dark:hover:bg-slate-800">Filter</button>
                    <a href="{{ route('audit-logs.index') }}" class="inline-flex h-9 items-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:text-blue-200 dark:hover:bg-blue-950/40">Clear</a>
                    <button type="button" data-skip-global-loader="true" x-on:click="openAuditPdfPreview(@js($auditPdfPreviewUrl), @js($auditPdfDownloadUrl), 'Audit Trail')" class="inline-flex h-9 items-center rounded-md border border-emerald-200 px-3 text-xs font-semibold text-emerald-700 hover:bg-emerald-50 dark:border-emerald-500/45 dark:text-emerald-200 dark:hover:bg-emerald-950/35">
                        Print PDF
                    </button>
                    <a href="{{ $auditPdfDownloadUrl }}" class="inline-flex h-9 items-center rounded-md border border-indigo-200 px-3 text-xs font-semibold text-indigo-700 hover:bg-indigo-50 dark:border-indigo-500/45 dark:text-indigo-200 dark:hover:bg-indigo-950/35">
                        Download PDF
                    </a>
                </div>
            </form>

            <section class="overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                <div class="hidden overflow-x-auto lg:block">
                    <table class="w-full min-w-[58rem] divide-y divide-blue-100 dark:divide-slate-800">
                        <thead class="bg-blue-50/70 dark:bg-slate-800/80">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-extrabold uppercase tracking-wide text-blue-800 dark:text-blue-200">Time</th>
                                <th class="px-5 py-3 text-left text-xs font-extrabold uppercase tracking-wide text-blue-800 dark:text-blue-200">Actor</th>
                                <th class="px-5 py-3 text-left text-xs font-extrabold uppercase tracking-wide text-blue-800 dark:text-blue-200">Action</th>
                                <th class="px-5 py-3 text-left text-xs font-extrabold uppercase tracking-wide text-blue-800 dark:text-blue-200">Diagnostic</th>
                                <th class="px-5 py-3 text-left text-xs font-extrabold uppercase tracking-wide text-blue-800 dark:text-blue-200">Result</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50 dark:divide-slate-800">
                            @forelse ($logs as $log)
                                <tr class="dark:bg-slate-900">
                                    <td class="px-5 py-4 text-sm text-slate-600 dark:text-slate-400">{{ $log->created_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</td>
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-slate-900 dark:text-slate-100">{{ $log->actor_name ?: 'System' }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200 dark:bg-blue-950/40 dark:text-blue-200 dark:ring-blue-700/60">
                                            {{ str($log->action)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td class="max-w-md px-5 py-4 text-sm text-slate-600 dark:text-slate-300">{{ $log->diagnosticSummary() }}</td>
                                    <td class="px-5 py-4">
                                        <span class="{{ $log->resultBadgeClasses() }}">{{ $log->resultLabel() }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-slate-500 dark:text-slate-400">No audit records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-3 p-3 sm:p-4 lg:hidden">
                    @forelse ($logs as $log)
                        <article class="min-w-0 rounded-md border border-blue-100 p-3 dark:border-slate-800 dark:bg-slate-900">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-blue-950 dark:text-slate-100">{{ $log->actor_name ?: 'System' }}</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $log->created_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
                                </div>
                                <span class="max-w-[7rem] shrink-0 truncate whitespace-nowrap rounded-md bg-blue-50 px-2 py-1 text-[0.65rem] font-semibold text-blue-700 ring-1 ring-blue-200 dark:bg-blue-950/40 dark:text-blue-200 dark:ring-blue-700/60 sm:px-2.5 sm:text-xs">
                                    {{ str($log->action)->replace('_', ' ')->title() }}
                                </span>
                            </div>
                            <dl class="mt-3 grid gap-3 text-xs sm:text-sm">
                                <div>
                                    <dt class="font-bold uppercase tracking-wide text-blue-800 dark:text-blue-200">Diagnostic</dt>
                                    <dd class="mt-1 text-slate-600 dark:text-slate-300">{{ $log->diagnosticSummary() }}</dd>
                                </div>
                                <div>
                                    <dt class="font-bold uppercase tracking-wide text-blue-800 dark:text-blue-200">Result</dt>
                                    <dd class="mt-1">
                                        <span class="{{ $log->resultBadgeClasses() }}">{{ $log->resultLabel() }}</span>
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    @empty
                        <div class="rounded-md border border-blue-100 px-5 py-8 text-center text-slate-500 dark:border-slate-800 dark:text-slate-400">No audit records found.</div>
                    @endforelse
                </div>

                <div class="border-t border-blue-100 px-5 py-4 dark:border-slate-800">
                    {{ $logs->links() }}
                </div>
            </section>
        </div>

        <div
            x-show="pdfPreviewOpen"
            x-cloak
            x-transition.opacity.duration.150ms
            class="fixed inset-0 z-[80] bg-slate-950/60"
            x-on:click="closeAuditPdfPreview()"
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
            aria-labelledby="audit-pdf-preview-title"
        >
            <div class="flex h-[min(92dvh,56rem)] w-full max-w-5xl flex-col overflow-hidden rounded-md border border-blue-100 bg-white shadow-2xl dark:border-slate-700 dark:bg-slate-900" x-on:click.stop>
                <div class="flex shrink-0 items-start justify-between gap-3 border-b border-blue-100 px-4 py-3 dark:border-slate-800 sm:px-5">
                    <div class="min-w-0">
                        <p class="text-[0.68rem] font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">PDF Preview</p>
                        <h3 id="audit-pdf-preview-title" class="mt-1 truncate text-base font-semibold text-blue-950 dark:text-blue-100" x-text="pdfPreviewTitle"></h3>
                    </div>
                    <button
                        type="button"
                        x-ref="auditPdfCloseButton"
                        x-on:click="closeAuditPdfPreview()"
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
                        title="Audit trail PDF preview"
                        class="h-full w-full border-0 bg-white"
                    ></iframe>
                </div>

                <div class="flex shrink-0 flex-col gap-2 border-t border-blue-100 px-4 py-3 dark:border-slate-800 sm:flex-row sm:items-center sm:justify-end sm:px-5">
                    <a
                        x-bind:href="pdfPreviewUrl"
                        target="_blank"
                        rel="noopener"
                        class="inline-flex h-10 items-center justify-center rounded-md border border-emerald-200 px-4 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 dark:border-emerald-500/45 dark:text-emerald-200 dark:hover:bg-emerald-950/35 dark:focus:ring-offset-slate-900"
                    >
                        Open / Print
                    </a>
                    <a
                        x-bind:href="pdfDownloadUrl"
                        class="inline-flex h-10 items-center justify-center rounded-md border border-indigo-200 px-4 text-sm font-semibold text-indigo-700 transition hover:bg-indigo-50 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:border-indigo-500/45 dark:text-indigo-200 dark:hover:bg-indigo-950/35 dark:focus:ring-offset-slate-900"
                    >
                        Download PDF
                    </a>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
