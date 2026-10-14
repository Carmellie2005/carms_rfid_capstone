<x-app-layout>
    <x-slot name="header">
        <div>
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950 dark:text-white">Device Diagnostics</h2>
                <p class="mt-1 text-sm text-blue-600 dark:text-slate-200">ESP32 connectivity, component health, and latest device reports</p>
            </div>
        </div>
    </x-slot>

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                @foreach ([
                    ['label' => 'Devices', 'value' => $summary['total'], 'cardClass' => 'border-blue-100 bg-white dark:border-slate-700 dark:bg-slate-900', 'labelClass' => 'text-blue-700 dark:text-blue-300', 'valueClass' => 'text-blue-950 dark:text-blue-100'],
                    ['label' => 'Online', 'value' => $summary['online'], 'cardClass' => 'border-emerald-100 bg-emerald-50/60 dark:border-emerald-400/35 dark:bg-emerald-950/25', 'labelClass' => 'text-emerald-700 dark:text-emerald-200', 'valueClass' => 'text-emerald-900 dark:text-emerald-100'],
                    ['label' => 'Offline', 'value' => $summary['offline'], 'cardClass' => 'border-amber-100 bg-amber-50/60 dark:border-amber-400/35 dark:bg-amber-950/25', 'labelClass' => 'text-amber-700 dark:text-amber-200', 'valueClass' => 'text-amber-900 dark:text-amber-100'],
                    ['label' => 'Device Issues', 'value' => $summary['deviceIssues'], 'cardClass' => 'border-red-100 bg-red-50/60 dark:border-red-400/35 dark:bg-red-950/25', 'labelClass' => 'text-red-700 dark:text-red-200', 'valueClass' => 'text-red-900 dark:text-red-100'],
                ] as $item)
                    <div class="min-h-[5.75rem] rounded-md border p-3 shadow-sm sm:p-5 {{ $item['cardClass'] }}">
                        <p class="truncate whitespace-nowrap text-[0.7rem] font-semibold uppercase tracking-wide sm:text-xs {{ $item['labelClass'] }}">{{ $item['label'] }}</p>
                        <p class="mt-2 text-2xl font-bold sm:text-3xl {{ $item['valueClass'] }}">{{ $item['value'] }}</p>
                    </div>
                @endforeach
            </section>

            <section class="grid grid-cols-1 gap-3 xl:grid-cols-2 lg:gap-4">
                @forelse ($checkpoints as $checkpoint)
                    @php
                        $state = $checkpoint->reader_state;
                        $stateConfig = [
                            'online' => ['label' => 'Online', 'class' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-400/45'],
                            'offline' => ['label' => 'Offline', 'class' => 'bg-amber-50 text-amber-700 ring-amber-200 dark:bg-amber-950/40 dark:text-amber-200 dark:ring-amber-400/45'],
                            'inactive' => ['label' => 'Inactive', 'class' => 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600'],
                            'no_device' => ['label' => 'No Device', 'class' => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-400/45'],
                        ][$state] ?? ['label' => 'Unknown', 'class' => 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600'];
                        $latest = $checkpoint->latestPatrolLog;
                        $diagnostics = $checkpoint->reader_diagnostics ?? [];
                        $statusBadgeClass = function (?string $status): string {
                            return match (strtolower((string) $status)) {
                                'online', 'connected', 'ready' => 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-200 dark:ring-emerald-400/45',
                                'offline', 'failed', 'error', 'disconnected' => 'bg-red-50 text-red-700 ring-red-200 dark:bg-red-950/40 dark:text-red-200 dark:ring-red-400/45',
                                default => 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-200 dark:ring-slate-600',
                            };
                        };
                        $statusLabel = fn (?string $status): string => filled($status)
                            ? \Illuminate\Support\Str::headline($status)
                            : 'Not Reported';
                    @endphp

                    <article class="min-w-0 rounded-md border border-blue-100 bg-white p-3 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-mono text-xs font-semibold uppercase text-blue-700 dark:text-blue-300">{{ $checkpoint->code }}</p>
                                <h3 class="mt-1 truncate text-sm font-semibold text-blue-950 dark:text-white sm:text-lg">{{ $checkpoint->name }}</h3>
                                <p class="mt-1 truncate text-xs text-slate-500 dark:text-slate-400 sm:text-sm">{{ $checkpoint->location }}</p>
                            </div>
                            <span class="inline-flex w-fit whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 sm:px-3 {{ $stateConfig['class'] }}">
                                {{ $stateConfig['label'] }}
                            </span>
                        </div>

                        <dl class="mt-4 grid gap-2 sm:mt-5 sm:grid-cols-2 sm:gap-3">
                            <div class="rounded-md bg-blue-50/60 p-2 dark:bg-slate-800/80 sm:p-3">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800 dark:text-blue-200 sm:text-xs">Device UID</dt>
                                <dd class="mt-1 truncate font-mono text-xs text-slate-800 dark:text-slate-100 sm:text-sm">{{ $checkpoint->device_uid ?: 'Not assigned' }}</dd>
                            </div>
                            <div class="rounded-md bg-blue-50/60 p-2 dark:bg-slate-800/80 sm:p-3">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800 dark:text-blue-200 sm:text-xs">Device IP</dt>
                                <dd class="mt-1 truncate font-mono text-xs text-slate-800 dark:text-slate-100 sm:text-sm">{{ $diagnostics['local_ip'] ?? 'Not reported' }}</dd>
                            </div>
                            <div class="rounded-md bg-blue-50/60 p-2 dark:bg-slate-800/80 sm:p-3">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800 dark:text-blue-200 sm:text-xs">Last Seen</dt>
                                <dd class="mt-1 text-xs font-medium text-slate-800 dark:text-slate-100 sm:text-sm">
                                    {{ $checkpoint->reader_seen_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? 'No reader activity' }}
                                </dd>
                            </div>
                            <div class="rounded-md bg-blue-50/60 p-2 dark:bg-slate-800/80 sm:p-3">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800 dark:text-blue-200 sm:text-xs">Latest Scan</dt>
                                <dd class="mt-1 text-xs font-medium text-slate-800 dark:text-slate-100 sm:text-sm">
                                    {{ $latest?->scanned_at?->timezone('Asia/Manila')->format('M d, Y h:i A') ?? 'No scan yet' }}
                                </dd>
                            </div>
                        </dl>

                        <div class="mt-3 rounded-md border border-blue-100 bg-blue-50/40 p-3 dark:border-slate-700 dark:bg-slate-950/60 sm:mt-4">
                            <div class="flex items-center justify-between gap-3">
                                <h4 class="text-xs font-semibold uppercase text-blue-800 dark:text-blue-200">Component Status</h4>
                                @if ($checkpoint->reader_has_device_issue)
                                    <span class="rounded-md bg-red-50 px-2 py-1 text-[0.65rem] font-semibold uppercase text-red-700 ring-1 ring-red-200 dark:bg-red-950/45 dark:text-red-200 dark:ring-red-400/45">Needs Check</span>
                                @endif
                            </div>

                            <dl class="mt-3 grid gap-2 sm:grid-cols-2">
                                @foreach ([
                                    ['label' => 'WiFi', 'icon' => 'wifi', 'status' => $diagnostics['wifi_status'] ?? null],
                                    ['label' => 'RFID Reader', 'icon' => 'reader', 'status' => $diagnostics['rfid_status'] ?? null],
                                    ['label' => 'LCD', 'icon' => 'lcd', 'status' => $diagnostics['lcd_status'] ?? null],
                                    ['label' => 'Buzzer', 'icon' => 'buzzer', 'status' => $diagnostics['buzzer_status'] ?? null],
                                ] as $component)
                                    <div class="flex items-center justify-between gap-3 rounded-md bg-white px-3 py-2 ring-1 ring-blue-100 dark:bg-slate-900 dark:ring-slate-700">
                                        <dt class="flex min-w-0 items-center gap-2 text-xs font-semibold text-slate-600 dark:text-slate-300">
                                            <span class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100 dark:bg-slate-800 dark:text-blue-200 dark:ring-slate-700">
                                                @switch($component['icon'])
                                                    @case('wifi')
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19.5h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                                        </svg>
                                                        @break
                                                    @case('reader')
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M4 7h10v10H4V7Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                                            <path d="M7 10h4M7 14h2M17 8c1.3 1.5 1.3 6.5 0 8M20 5c3 3.4 3 10.6 0 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                                        </svg>
                                                        @break
                                                    @case('lcd')
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M5 5h14v10H5V5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                                            <path d="M9 19h6M12 15v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                                        </svg>
                                                        @break
                                                    @default
                                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                            <path d="M4 10h3l4-4v12l-4-4H4v-4Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                                            <path d="M15 9c1.2 1.4 1.2 4.6 0 6M18 6c2.5 3 2.5 9 0 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                                        </svg>
                                                @endswitch
                                            </span>
                                            <span class="truncate">{{ $component['label'] }}</span>
                                        </dt>
                                        <dd class="shrink-0">
                                            <span class="inline-flex rounded-md px-2 py-1 text-[0.7rem] font-semibold ring-1 {{ $statusBadgeClass($component['status']) }}">
                                                {{ $statusLabel($component['status']) }}
                                            </span>
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>

                            <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                <div class="rounded-md bg-white px-3 py-2 ring-1 ring-blue-100 dark:bg-slate-900 dark:ring-slate-700">
                                    <p class="text-[0.65rem] font-semibold uppercase text-slate-500 dark:text-slate-400">WiFi Signal</p>
                                    <p class="mt-1 truncate font-mono text-xs font-medium text-slate-700 dark:text-slate-200">
                                        {{ filled($diagnostics['wifi_rssi'] ?? null) ? $diagnostics['wifi_rssi'].' dBm' : 'Not reported' }}
                                    </p>
                                </div>
                                <div class="rounded-md bg-white px-3 py-2 ring-1 ring-blue-100 dark:bg-slate-900 dark:ring-slate-700">
                                    <p class="text-[0.65rem] font-semibold uppercase text-slate-500 dark:text-slate-400">Firmware</p>
                                    <p class="mt-1 truncate font-mono text-xs font-medium text-slate-700 dark:text-slate-200">{{ $diagnostics['firmware'] ?? 'Not reported' }}</p>
                                </div>
                                <div class="rounded-md bg-white px-3 py-2 ring-1 ring-blue-100 dark:bg-slate-900 dark:ring-slate-700">
                                    <p class="text-[0.65rem] font-semibold uppercase text-slate-500 dark:text-slate-400">Last Message</p>
                                    <p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                                        {{ $checkpoint->reader_last_message ?: 'No heartbeat message yet.' }}
                                    </p>
                                </div>
                                <div class="rounded-md bg-white px-3 py-2 ring-1 ring-blue-100 dark:bg-slate-900 dark:ring-slate-700">
                                    <p class="text-[0.65rem] font-semibold uppercase text-slate-500 dark:text-slate-400">Error Status</p>
                                    <p class="mt-1 text-xs font-medium text-slate-600 dark:text-slate-300">
                                        {{ filled($diagnostics['last_error'] ?? null) ? $diagnostics['last_error'] : 'No device errors reported.' }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="rounded-md border border-blue-100 bg-white px-5 py-8 text-center text-slate-500 shadow-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-400 xl:col-span-2">
                        No checkpoints registered.
                    </div>
                @endforelse
            </section>

        </div>
    </div>
</x-app-layout>
