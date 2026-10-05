<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950 dark:text-white">{{ __('Data Viewer') }}</h2>
                <p class="mt-1 text-sm text-blue-600 dark:text-slate-200">Read-only system records for supervisor review</p>
            </div>
        </div>
    </x-slot>

    @php
        $badgeClasses = [
            'success' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/35 dark:text-emerald-200 dark:ring-emerald-400/45',
            'warning' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/35 dark:text-amber-200 dark:ring-amber-400/45',
            'danger' => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/35 dark:text-red-200 dark:ring-red-400/45',
            'info' => 'bg-blue-50 text-blue-700 ring-blue-200 dark:bg-blue-950/35 dark:text-blue-200 dark:ring-blue-400/45',
            'neutral' => 'bg-slate-50 text-slate-700 ring-slate-200 dark:bg-slate-950/50 dark:text-slate-200 dark:ring-slate-500/60',
        ];

        $tabUrl = fn (string $dataset) => route('data-viewer.index', array_merge(request()->except(['dataset', 'page']), ['dataset' => $dataset]));
        $clearUrl = route('data-viewer.index', ['dataset' => $activeDataset]);
    @endphp

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-[96rem] space-y-5 px-4 sm:px-6 lg:px-8">
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                @foreach ($datasets as $key => $dataset)
                    <a
                        href="{{ $tabUrl($key) }}"
                        class="rounded-md border p-4 shadow-sm transition focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950 {{ $activeDataset === $key ? 'border-blue-300 bg-blue-50 text-blue-950 dark:border-blue-500/60 dark:bg-blue-950/40 dark:text-blue-100' : 'border-blue-100 bg-white text-slate-800 hover:border-blue-200 hover:bg-blue-50/50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800' }}"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-sm font-bold">{{ $dataset['label'] }}</p>
                                <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-300">{{ $dataset['description'] }}</p>
                            </div>
                            <span class="rounded-md bg-white px-2 py-1 text-xs font-bold text-blue-700 ring-1 ring-blue-100 dark:bg-slate-950 dark:text-blue-200 dark:ring-slate-700">
                                {{ number_format($datasetCounts[$key] ?? 0) }}
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>

            <section class="overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <div class="border-b border-blue-100 px-4 py-4 dark:border-slate-700 sm:px-5">
                    <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">Current Table</p>
                            <h3 class="mt-1 text-lg font-semibold text-blue-950 dark:text-white">{{ $activeConfig['label'] }}</h3>
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-300">{{ $activeConfig['description'] }}</p>
                        </div>

                        <form method="GET" action="{{ route('data-viewer.index') }}" class="grid gap-2 sm:grid-cols-2 lg:w-[44rem] lg:grid-cols-[minmax(0,1fr)_11rem_10rem_auto_auto]">
                            <input type="hidden" name="dataset" value="{{ $activeDataset }}">

                            <div class="sm:col-span-2 lg:col-span-1">
                                <label for="q" class="sr-only">Search records</label>
                                <input
                                    id="q"
                                    name="q"
                                    value="{{ $filters['q'] }}"
                                    placeholder="Search records"
                                    class="block h-10 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder-slate-500"
                                >
                            </div>

                            @if (! empty($activeConfig['filter_options']))
                                <div>
                                    <label for="status" class="sr-only">{{ $activeConfig['filter_label'] }}</label>
                                    <select id="status" name="status" class="block h-10 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                                        <option value="">{{ $activeConfig['filter_label'] ?? 'Filter' }}: All</option>
                                        @foreach ($activeConfig['filter_options'] as $value => $label)
                                            <option value="{{ $value }}" @selected($filters['status'] === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @endif

                            <div>
                                <label for="date" class="sr-only">Date</label>
                                <input id="date" name="date" type="date" value="{{ $filters['date'] }}" class="block h-10 w-full rounded-md border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100">
                            </div>

                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                                Filter
                            </button>

                            <a href="{{ $clearUrl }}" class="inline-flex h-10 items-center justify-center rounded-md border border-blue-200 px-4 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:text-blue-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950">
                                Clear
                            </a>
                        </form>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm dark:divide-slate-700">
                        <thead class="bg-blue-50/70 text-left text-xs font-extrabold uppercase text-blue-800 dark:bg-slate-950/60 dark:text-white">
                            <tr>
                                @foreach ($activeConfig['columns'] as $label)
                                    <th class="whitespace-nowrap px-5 py-3">{{ $label }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50 dark:divide-slate-800">
                            @forelse ($records as $record)
                                <tr class="align-top dark:text-slate-100">
                                    @foreach (array_keys($activeConfig['columns']) as $column)
                                        @php($cell = $record[$column] ?? ['type' => 'text', 'value' => 'N/A'])
                                        <td class="px-5 py-4">
                                            @if (($cell['type'] ?? 'text') === 'badge')
                                                <span class="inline-flex max-w-[14rem] whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $badgeClasses[$cell['tone'] ?? 'neutral'] ?? $badgeClasses['neutral'] }}">
                                                    {{ $cell['value'] }}
                                                </span>
                                            @elseif (($cell['type'] ?? 'text') === 'link')
                                                <a href="{{ $cell['href'] }}" target="_blank" rel="noopener" class="inline-flex h-9 items-center justify-center rounded-md border border-blue-200 bg-white px-3 text-xs font-bold text-blue-700 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-blue-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950">
                                                    {{ $cell['value'] }}
                                                </a>
                                            @else
                                                <div class="min-w-0 {{ ! empty($cell['mono']) ? 'font-mono' : '' }}">
                                                    <p class="max-w-[18rem] truncate font-medium text-slate-900 dark:text-slate-100" title="{{ $cell['value'] }}">{{ $cell['value'] }}</p>
                                                    @if (! empty($cell['subvalue']))
                                                        <p class="mt-1 max-w-[18rem] truncate text-xs text-slate-500 dark:text-slate-400" title="{{ $cell['subvalue'] }}">{{ $cell['subvalue'] }}</p>
                                                    @endif
                                                </div>
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ count($activeConfig['columns']) }}" class="px-5 py-10 text-center text-sm text-slate-500 dark:text-slate-300">
                                        No {{ strtolower($activeConfig['label']) }} records found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                <x-pagination-panel :paginator="$records" :label="strtolower($activeConfig['label']).' records'" :page-label="$activeConfig['label'].' page'" class="border-t border-blue-100 px-5 py-4 dark:border-slate-700" />
            </section>
        </div>
    </div>
</x-app-layout>
