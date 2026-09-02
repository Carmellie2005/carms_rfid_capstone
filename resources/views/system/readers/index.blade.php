<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">RFID Reader Status</h2>
                <p class="mt-1 text-sm text-blue-600">Checkpoint devices and reader health</p>
            </div>
            <a href="{{ route('checkpoints.index') }}" class="inline-flex w-full items-center justify-center rounded-md border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 sm:w-auto">
                Manage Checkpoints
            </a>
        </div>
    </x-slot>

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ([
                    ['label' => 'Readers', 'value' => $summary['total'], 'cardClass' => 'border-blue-100 bg-white', 'labelClass' => 'text-blue-700', 'valueClass' => 'text-blue-950'],
                    ['label' => 'Online', 'value' => $summary['online'], 'cardClass' => 'border-emerald-100 bg-emerald-50/60', 'labelClass' => 'text-emerald-700', 'valueClass' => 'text-emerald-900'],
                    ['label' => 'Offline', 'value' => $summary['offline'], 'cardClass' => 'border-amber-100 bg-amber-50/60', 'labelClass' => 'text-amber-700', 'valueClass' => 'text-amber-900'],
                    ['label' => 'Needs Review', 'value' => $summary['troubleScans'], 'cardClass' => 'border-red-100 bg-red-50/60', 'labelClass' => 'text-red-700', 'valueClass' => 'text-red-900'],
                ] as $item)
                    <div class="min-h-[5.75rem] rounded-md border p-3 shadow-sm sm:p-5 {{ $item['cardClass'] }}">
                        <p class="truncate whitespace-nowrap text-[0.7rem] font-semibold uppercase tracking-wide sm:text-xs {{ $item['labelClass'] }}">{{ $item['label'] }}</p>
                        <p class="mt-2 text-2xl font-bold sm:text-3xl {{ $item['valueClass'] }}">{{ $item['value'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="grid grid-cols-2 gap-3 lg:gap-4">
                @forelse ($checkpoints as $checkpoint)
                    @php
                        $state = $checkpoint->reader_state;
                        $stateConfig = [
                            'online' => ['label' => 'Online', 'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-200'],
                            'offline' => ['label' => 'Offline', 'class' => 'bg-amber-50 text-amber-700 ring-amber-200'],
                            'inactive' => ['label' => 'Inactive', 'class' => 'bg-slate-50 text-slate-600 ring-slate-200'],
                            'no_device' => ['label' => 'No Device', 'class' => 'bg-red-50 text-red-700 ring-red-200'],
                        ][$state] ?? ['label' => 'Unknown', 'class' => 'bg-slate-50 text-slate-600 ring-slate-200'];
                        $latest = $checkpoint->latestPatrolLog;
                    @endphp

                    <article class="min-w-0 rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-mono text-xs font-semibold uppercase text-blue-700">{{ $checkpoint->code }}</p>
                                <h3 class="mt-1 truncate text-sm font-semibold text-blue-950 sm:text-lg">{{ $checkpoint->name }}</h3>
                                <p class="mt-1 truncate text-xs text-slate-500 sm:text-sm">{{ $checkpoint->location }}</p>
                            </div>
                            <span class="inline-flex w-fit whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 sm:px-3 {{ $stateConfig['class'] }}">
                                {{ $stateConfig['label'] }}
                            </span>
                        </div>

                        <dl class="mt-4 grid gap-2 sm:mt-5 sm:grid-cols-2 sm:gap-3">
                            <div class="rounded-md bg-blue-50/60 p-2 sm:p-3">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800 sm:text-xs">Device UID</dt>
                                <dd class="mt-1 truncate font-mono text-xs text-slate-800 sm:text-sm">{{ $checkpoint->device_uid ?: 'Not assigned' }}</dd>
                            </div>
                            <div class="rounded-md bg-blue-50/60 p-2 sm:p-3">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800 sm:text-xs">Last Seen</dt>
                                <dd class="mt-1 text-xs font-medium text-slate-800 sm:text-sm">
                                    {{ $checkpoint->reader_seen_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? 'No reader activity' }}
                                </dd>
                            </div>
                            <div class="rounded-md bg-blue-50/60 p-2 sm:p-3">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800 sm:text-xs">Reader IP</dt>
                                <dd class="mt-1 truncate font-mono text-xs text-slate-800 sm:text-sm">{{ $checkpoint->reader_last_ip ?: 'Not recorded' }}</dd>
                            </div>
                            <div class="rounded-md bg-blue-50/60 p-2 sm:p-3">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800 sm:text-xs">Latest Scan</dt>
                                <dd class="mt-1 text-xs font-medium text-slate-800 sm:text-sm">
                                    {{ $latest?->scanned_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? 'No scan yet' }}
                                </dd>
                            </div>
                        </dl>

                        @if ($checkpoint->reader_last_message)
                            <p class="mt-3 rounded-md border border-blue-100 bg-white px-3 py-2 text-xs text-slate-600 sm:mt-4 sm:text-sm">
                                {{ $checkpoint->reader_last_message }}
                            </p>
                        @endif
                    </article>
                @empty
                    <div class="col-span-2 rounded-md border border-blue-100 bg-white px-5 py-8 text-center text-slate-500 shadow-sm">
                        No checkpoints registered.
                    </div>
                @endforelse
            </section>

        </div>
    </div>
</x-app-layout>
