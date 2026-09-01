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

            <form method="GET" action="{{ route('audit-logs.index') }}" class="grid gap-3 rounded-lg border border-blue-100 bg-white p-3 shadow-sm md:grid-cols-2 xl:grid-cols-[minmax(170px,1fr)_minmax(170px,1fr)_minmax(140px,0.75fr)_minmax(220px,1.2fr)_auto] print:hidden">
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
                    <label for="search" class="block text-xs font-semibold uppercase text-blue-800">Search</label>
                    <input id="search" name="search" value="{{ request('search') }}" placeholder="Actor, action, IP, or description" class="mt-1 block h-9 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                </div>
                <div class="flex flex-wrap items-end gap-2 md:col-span-2 xl:col-span-1 xl:self-end xl:justify-end">
                    <button type="submit" class="h-9 rounded-md bg-blue-700 px-3 text-xs font-semibold text-white hover:bg-blue-800">Filter</button>
                    <a href="{{ route('audit-logs.index') }}" class="inline-flex h-9 items-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">Clear</a>
                    <a href="{{ route('audit-logs.pdf', $exportQuery) }}" class="inline-flex h-9 items-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                        Download PDF
                    </a>
                    <a href="{{ route('audit-logs.pdf', array_merge($exportQuery, ['print' => 1])) }}" target="_blank" rel="noopener" class="inline-flex h-9 items-center rounded-md border border-blue-200 px-3 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                        Print PDF
                    </a>
                </div>
            </form>

            <section class="overflow-hidden rounded-lg border border-blue-100 bg-white shadow-sm">
                <div class="hidden overflow-x-auto lg:block">
                    <table class="min-w-full divide-y divide-blue-100">
                        <thead class="bg-blue-50/70">
                            <tr>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Time</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Actor</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Action</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Description</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">IP</th>
                                <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-blue-800">Details</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            @forelse ($logs as $log)
                                <tr>
                                    <td class="px-5 py-4 text-sm text-slate-600">{{ $log->created_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</td>
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-slate-900">{{ $log->actor_name ?: 'System' }}</div>
                                        <div class="text-xs text-slate-500">{{ $log->user?->email }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
                                            {{ str($log->action)->replace('_', ' ')->title() }}
                                        </span>
                                    </td>
                                    <td class="max-w-md px-5 py-4 text-sm text-slate-600">{{ $log->description }}</td>
                                    <td class="px-5 py-4 font-mono text-sm text-slate-600">{{ $log->ip_address ?: 'N/A' }}</td>
                                    <td class="px-5 py-4">
                                        @if ($log->properties)
                                            <details class="text-sm">
                                                <summary class="cursor-pointer font-semibold text-blue-700">View</summary>
                                                <pre class="mt-2 max-w-sm overflow-x-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">{{ json_encode($log->properties, JSON_PRETTY_PRINT) }}</pre>
                                            </details>
                                        @else
                                            <span class="text-sm text-slate-400">None</span>
                                        @endif
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

                <div class="grid gap-3 p-4 lg:hidden">
                    @forelse ($logs as $log)
                        <article class="rounded-lg border border-blue-100 p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold text-blue-950">{{ $log->actor_name ?: 'System' }}</p>
                                    <p class="mt-1 text-xs text-slate-500">{{ $log->created_at->timezone('Asia/Manila')->format('M d, Y h:i A') }}</p>
                                </div>
                                <span class="rounded-full bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200">
                                    {{ str($log->action)->replace('_', ' ')->title() }}
                                </span>
                            </div>
                            <p class="mt-3 text-sm text-slate-600">{{ $log->description }}</p>
                            <p class="mt-2 font-mono text-xs text-slate-500">{{ $log->ip_address ?: 'N/A' }}</p>
                            @if ($log->properties)
                                <details class="mt-3 text-sm">
                                    <summary class="cursor-pointer font-semibold text-blue-700">Details</summary>
                                    <pre class="mt-2 overflow-x-auto rounded-md bg-slate-950 p-3 text-xs text-slate-100">{{ json_encode($log->properties, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @endif
                        </article>
                    @empty
                        <div class="rounded-lg border border-blue-100 px-5 py-8 text-center text-slate-500">No audit records found.</div>
                    @endforelse
                </div>

                <div class="border-t border-blue-100 px-5 py-4">
                    {{ $logs->links() }}
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
