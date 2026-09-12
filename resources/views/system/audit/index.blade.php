<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-blue-950">Audit Trail</h2>
            <p class="mt-1 text-sm text-blue-600">System activity and important security events</p>
        </div>
    </x-slot>

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @php
                $exportQuery = request()->only(['guard_id', 'action', 'date', 'search']);
            @endphp

            <form method="GET" action="{{ route('audit-logs.index') }}" class="grid gap-3 rounded-md border border-blue-100 bg-white p-3 shadow-sm md:grid-cols-2 xl:grid-cols-[minmax(170px,1fr)_minmax(170px,1fr)_minmax(140px,0.75fr)_minmax(220px,1.2fr)_auto] print:hidden">
                <div>
                    <label for="guard_id" class="block text-xs font-semibold uppercase text-blue-800">Guard</label>
                    <select id="guard_id" name="guard_id" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All guards</option>
                        @foreach ($guards as $guard)
                            <option value="{{ $guard->id }}" @selected((string) request('guard_id') === (string) $guard->id)>{{ $guard->name }} - {{ $guard->employee_no }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="action" class="block text-xs font-semibold uppercase text-blue-800">Action</label>
                    <select id="action" name="action" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">All actions</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(request('action') === $action)>{{ str($action)->replace('_', ' ')->title() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="date" class="block text-xs font-semibold uppercase text-blue-800">Date</label>
                    <input id="date" name="date" type="date" value="{{ request('date') }}" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div>
                    <label for="search" class="sr-only">Search</label>
                    <input id="search" name="search" value="{{ request('search') }}" placeholder="Actor, action, or diagnostic" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-1 xl:self-end xl:justify-end">
                    <button type="submit" class="h-9 rounded-md border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 shadow-sm hover:bg-slate-50">Filter</button>
                    <a href="{{ route('audit-logs.index') }}" class="inline-flex h-9 items-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">Clear</a>
                    <a href="{{ route('audit-logs.pdf', $exportQuery) }}" class="inline-flex h-9 items-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                        Download PDF
                    </a>
                    <a href="{{ route('audit-logs.pdf', array_merge($exportQuery, ['print' => 1])) }}" target="_blank" rel="noopener" class="inline-flex h-9 items-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                        Print PDF
                    </a>
                </div>
            </form>

            <section class="overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm">
                <div class="hidden overflow-x-auto lg:block">
                    <table class="w-full min-w-[66rem] divide-y divide-blue-100">
                        <thead class="bg-blue-50/70">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-extrabold uppercase tracking-wide text-blue-800">Time</th>
                                <th class="px-5 py-3 text-left text-xs font-extrabold uppercase tracking-wide text-blue-800">Actor</th>
                                <th class="px-5 py-3 text-left text-xs font-extrabold uppercase tracking-wide text-blue-800">Action</th>
                                <th class="px-5 py-3 text-left text-xs font-extrabold uppercase tracking-wide text-blue-800">Diagnostic</th>
                                <th class="px-5 py-3 text-left text-xs font-extrabold uppercase tracking-wide text-blue-800">Patrol Window</th>
                                <th class="px-5 py-3 text-left text-xs font-extrabold uppercase tracking-wide text-blue-800">Result</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            @forelse ($logs as $log)
                                <tr>
                                    <td class="px-5 py-4 text-sm text-slate-600">{{ $log->created_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</td>
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-slate-900">{{ $log->actor_name ?: 'System' }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
                                            {{ str($log->action)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td class="max-w-md px-5 py-4 text-sm text-slate-600">{{ $log->diagnosticSummary() }}</td>
                                    <td class="px-5 py-4 text-sm font-semibold text-slate-700">{{ $log->patrolWindowSummary() }}</td>
                                    <td class="px-5 py-4">
                                        <span class="{{ $log->resultBadgeClasses() }}">{{ $log->resultLabel() }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="px-5 py-8 text-center text-slate-500">No audit records found.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <div class="grid gap-3 p-3 sm:p-4 lg:hidden">
                    @forelse ($logs as $log)
                        <article class="min-w-0 rounded-md border border-blue-100 p-3">
                            <div class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-blue-950">{{ $log->actor_name ?: 'System' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $log->created_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
                                </div>
                                <span class="max-w-[7rem] shrink-0 truncate whitespace-nowrap rounded-md bg-blue-50 px-2 py-1 text-[0.65rem] font-semibold text-blue-700 ring-1 ring-blue-200 sm:px-2.5 sm:text-xs">
                                    {{ str($log->action)->replace('_', ' ')->title() }}
                                </span>
                            </div>
                            <dl class="mt-3 grid gap-3 text-xs sm:text-sm">
                                <div>
                                    <dt class="font-bold uppercase tracking-wide text-blue-800">Diagnostic</dt>
                                    <dd class="mt-1 text-slate-600">{{ $log->diagnosticSummary() }}</dd>
                                </div>
                                <div>
                                    <dt class="font-bold uppercase tracking-wide text-blue-800">Patrol Window</dt>
                                    <dd class="mt-1 font-semibold text-slate-700">{{ $log->patrolWindowSummary() }}</dd>
                                </div>
                                <div>
                                    <dt class="font-bold uppercase tracking-wide text-blue-800">Result</dt>
                                    <dd class="mt-1">
                                        <span class="{{ $log->resultBadgeClasses() }}">{{ $log->resultLabel() }}</span>
                                    </dd>
                                </div>
                            </dl>
                        </article>
                    @empty
                        <div class="rounded-md border border-blue-100 px-5 py-8 text-center text-slate-500">No audit records found.</div>
                    @endforelse
                </div>

                <div class="border-t border-blue-100 px-5 py-4">
                    {{ $logs->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
