@php
    $isSupervisor = Auth::user()->role === 'admin';
    $homeRoute = $isSupervisor ? route('dashboard') : route('patrol.scan');
    $guardSidebarProfile = $isSupervisor ? null : Auth::user()->guardProfile;
    $guardSidebarFullName = trim($guardSidebarProfile?->name ?? Auth::user()->name);
    $guardSidebarNameParts = preg_split('/\s+/', $guardSidebarFullName) ?: [];
    $guardSidebarDisplayName = $guardSidebarFullName;

    if (count($guardSidebarNameParts) > 1) {
        $firstInitial = strtoupper(substr($guardSidebarNameParts[0], 0, 1));
        $lastName = $guardSidebarNameParts[count($guardSidebarNameParts) - 1];
        $guardSidebarDisplayName = trim($lastName.' '.$firstInitial.'.');
    }

    $guardSidebarEmployeeNo = $guardSidebarProfile?->employee_no ?? 'Account only';
    $guardSidebarRfid = $guardSidebarProfile?->rfid_uid ?: 'No UID';
    $guardSidebarShift = strtoupper($guardSidebarProfile?->shift ?? 'Unassigned shift');
    $guardSidebarPhotoUrl = Auth::user()->profile_photo_path
        ? asset('storage/'.Auth::user()->profile_photo_path)
        : asset('images/user-icons/guard-account.png');

    $linkClasses = fn ($active) => $active
        ? 'flex items-center gap-3 rounded-lg bg-blue-700 px-3 py-2.5 text-sm font-semibold text-white shadow-sm'
        : 'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-blue-50 hover:text-blue-800 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-blue-100';

    $subLinkClasses = fn ($active) => $active
        ? 'block rounded-md bg-blue-50 px-3 py-2 text-sm font-semibold text-blue-800 dark:bg-blue-950 dark:text-blue-100'
        : 'block rounded-md px-3 py-2 text-sm font-medium text-slate-600 hover:bg-blue-50 hover:text-blue-800 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-blue-100';

    $sectionButtonClasses = 'flex w-full items-center justify-between rounded-lg px-3 py-2.5 text-sm font-semibold text-slate-600 hover:bg-blue-50 hover:text-blue-800 dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-blue-100';
    $navIcon = fn ($icon, $class = 'h-5 w-5 shrink-0') => new \Illuminate\Support\HtmlString(match ($icon) {
        'dashboard' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 13h7V4H4v9Zm0 7h7v-5H4v5Zm9 0h7v-9h-7v9Zm0-11h7V4h-7v5Z" fill="currentColor" /></svg>',
        'users' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 18c0-2.2-1.8-4-4-4s-4 1.8-4 4M12 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM20 18c0-1.7-1-3.1-2.4-3.7M17 6.2a2.8 2.8 0 0 1 0 5.6M4 18c0-1.7 1-3.1 2.4-3.7M7 6.2a2.8 2.8 0 0 0 0 5.6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>',
        'checkpoints' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /><path d="M12 12.2a2.2 2.2 0 1 0 0-4.4 2.2 2.2 0 0 0 0 4.4Z" stroke="currentColor" stroke-width="2" /></svg>',
        'patrols' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M6 18.5c2.5 1.5 5.5 1.5 8 0 2.8-1.7 3.2-5.1.9-7.1L9.1 6.3C7.2 4.7 8.3 2 10.8 2H18" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /><path d="M17 2h1.5A2.5 2.5 0 0 1 21 4.5V6M6 22a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>',
        'incidents' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 9v4m0 4h.01M10.3 4.2 2.7 17.4A2 2 0 0 0 4.4 20h15.2a2 2 0 0 0 1.7-2.6L13.7 4.2a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>',
        'reports' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /><path d="M14 3v5h5M8.5 14h7M8.5 17h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>',
        'scan' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 5h5v5H5V5Zm9 0h5v5h-5V5ZM5 14h5v5H5v-5Zm10 1h4m-4 4h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>',
        'scan_issues' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 5h5v5H5V5Zm9 0h5v5h-5V5ZM5 14h5v5H5v-5Zm10 1h4m-2-2v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>',
        'readers' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 9.5h8v5H5v-5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /><path d="M7.5 17h3M7.5 7h3M16 8c1.3 1.2 1.3 4.8 0 6M19 5c3 3.4 3 10.6 0 14" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>',
        'audit' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 4h8l3 3v13H5V4h3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" /><path d="M16 4v4h3M8.5 11h7M8.5 14h7M8.5 17h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>',
        'profile' => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" /></svg>',
        default => '<svg class="'.$class.'" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 7.5A2.5 2.5 0 0 1 7.5 5h9A2.5 2.5 0 0 1 19 7.5v9a2.5 2.5 0 0 1-2.5 2.5h-9A2.5 2.5 0 0 1 5 16.5v-9Z" stroke="currentColor" stroke-width="2" /><path d="M8.5 9.5h7m-7 5h7" stroke="currentColor" stroke-width="2" stroke-linecap="round" /></svg>',
    });

    $sections = [
        [
            'label' => 'Patrols',
            'icon' => 'patrols',
            'active' => request()->routeIs('patrol-logs.*'),
            'items' => [
                ['label' => 'Patrol Logs', 'href' => route('patrol-logs.index'), 'active' => request()->routeIs('patrol-logs.*')],
            ],
        ],
        [
            'label' => 'Incidents',
            'icon' => 'incidents',
            'active' => request()->routeIs('incidents.*'),
            'items' => [
                ['label' => 'Incident Reports', 'href' => route('incidents.index'), 'active' => request()->routeIs('incidents.*')],
            ],
        ],
    ];

    $managementSections = [];
    $monitoringSections = $isSupervisor ? $sections : [];

    $collapsedLinkClasses = fn ($active) => $active
        ? 'group relative inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-700 text-white shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-white dark:focus:ring-offset-slate-900'
        : 'group relative inline-flex h-10 w-10 items-center justify-center rounded-lg text-slate-600 transition hover:bg-blue-50 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-white dark:text-slate-300 dark:hover:bg-slate-800 dark:hover:text-blue-100 dark:focus:ring-offset-slate-900';

    $collapsedItems = $isSupervisor
        ? [
            ['label' => 'Dashboard', 'href' => route('dashboard'), 'icon' => 'dashboard', 'active' => request()->routeIs('dashboard')],
            ['label' => 'Guards', 'href' => route('guards.index'), 'icon' => 'users', 'active' => request()->routeIs('guards.*')],
            ['label' => 'Checkpoints', 'href' => route('checkpoints.index'), 'icon' => 'checkpoints', 'active' => request()->routeIs('checkpoints.*')],
            ['label' => 'Patrol Logs', 'href' => route('patrol-logs.index'), 'icon' => 'patrols', 'active' => request()->routeIs('patrol-logs.*')],
            ['label' => 'Scan Issues', 'href' => route('scan-issues.index'), 'icon' => 'scan_issues', 'active' => request()->routeIs('scan-issues.*')],
            ['label' => 'Incidents', 'href' => route('incidents.index'), 'icon' => 'incidents', 'active' => request()->routeIs('incidents.*')],
            ['label' => 'Readers', 'href' => route('readers.index'), 'icon' => 'readers', 'active' => request()->routeIs('readers.*')],
            ['label' => 'Audit Trail', 'href' => route('audit-logs.index'), 'icon' => 'audit', 'active' => request()->routeIs('audit-logs.*')],
            ['label' => 'Reports', 'href' => route('reports.index'), 'icon' => 'reports', 'active' => request()->routeIs('reports.*')],
        ]
        : [
            ['label' => 'Scan Checkpoint', 'href' => route('patrol.scan'), 'icon' => 'scan', 'active' => request()->routeIs('patrol.scan')],
            ['label' => 'My Patrol Logs', 'href' => route('patrol-logs.index'), 'icon' => 'patrols', 'active' => request()->routeIs('patrol-logs.*')],
            ['label' => 'Profile', 'href' => route('profile.edit'), 'icon' => 'profile', 'active' => request()->routeIs('profile.edit')],
        ];
@endphp

<nav aria-label="System navigation">
    <button
        type="button"
        x-show="! sidebarOpen"
        class="fixed left-3 top-3 z-50 inline-flex h-10 w-10 items-center justify-center rounded-lg border border-blue-100 bg-white text-slate-900 shadow-sm hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-blue-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950 lg:hidden"
        @click="sidebarOpen = true"
        aria-label="Open sidebar"
    >
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
        </svg>
    </button>

    <div
        x-show="sidebarCollapsed"
        style="display: none;"
        class="fixed inset-y-0 left-0 z-40 hidden w-16 flex-col items-center border-r border-blue-100 bg-white px-2 py-5 dark:border-slate-800 dark:bg-slate-900 lg:flex"
    >
        <button
            type="button"
            class="inline-flex h-10 w-10 items-center justify-center rounded-lg border border-blue-100 bg-white text-slate-900 shadow-sm hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-blue-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950"
            @click="sidebarCollapsed = false"
            aria-label="Open sidebar"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
            </svg>
        </button>

        <div class="sidebar-scroll-area mt-6 flex w-full flex-1 flex-col items-center gap-2 overflow-y-auto pb-4">
            @foreach ($collapsedItems as $item)
                <a href="{{ $item['href'] }}" class="{{ $collapsedLinkClasses($item['active']) }}" title="{{ $item['label'] }}" aria-label="{{ $item['label'] }}">
                    {!! $navIcon($item['icon']) !!}
                    <span class="pointer-events-none absolute left-full z-50 ml-3 hidden whitespace-nowrap rounded-md bg-slate-950 px-2 py-1 text-xs font-semibold text-white shadow-lg group-hover:block group-focus-visible:block dark:bg-slate-100 dark:text-slate-950">
                        {{ $item['label'] }}
                    </span>
                </a>
            @endforeach
        </div>
    </div>

    <div
        x-show="sidebarOpen"
        style="display: none;"
        class="fixed inset-0 z-40 bg-slate-950/40 lg:hidden"
        @click="sidebarOpen = false"
        aria-hidden="true"
    ></div>

    <aside
        class="fixed inset-y-0 left-0 z-50 flex w-[min(18rem,calc(100vw-1.25rem))] -translate-x-full flex-col border-r border-blue-100 bg-white shadow-xl dark:border-slate-800 dark:bg-slate-900 lg:w-72 lg:translate-x-0 lg:shadow-none"
        :class="{
            '!translate-x-0': sidebarOpen,
            'lg:!translate-x-0': ! sidebarCollapsed,
            'lg:!-translate-x-full': sidebarCollapsed
        }"
    >
        <div class="sidebar-brand-soft flex h-20 items-center justify-between border-b border-blue-100 px-5 dark:border-slate-800">
            <a href="{{ $homeRoute }}" class="flex min-w-0 flex-1 items-center gap-3" aria-label="SLSU Bontoc Patrol dashboard" @click="sidebarOpen = false">
                <x-application-logo class="h-11 w-11 shrink-0" />
                <span class="min-w-0 leading-tight">
                    <span class="block text-sm font-bold text-blue-950 dark:text-blue-100">SLSU Bontoc Patrol</span>
                    <span class="block text-xs font-medium text-blue-500 dark:text-blue-300">Security Monitoring</span>
                </span>
            </a>

            <button
                type="button"
                class="ml-3 inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-blue-100 bg-white text-slate-900 shadow-sm hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-blue-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950"
                @click="window.innerWidth >= 1024 ? sidebarCollapsed = true : sidebarOpen = false"
                aria-label="Toggle sidebar"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M4 6h16M4 12h16M4 18h16" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
            </button>
        </div>

        <div class="sidebar-scroll-area flex flex-1 flex-col overflow-y-auto px-4 py-5">
            <div class="space-y-1">
                <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Main</p>
                @if ($isSupervisor)
                    <a href="{{ route('dashboard') }}" class="{{ $linkClasses(request()->routeIs('dashboard')) }}" @click="sidebarOpen = false">
                        {!! $navIcon('dashboard') !!}
                        <span>Dashboard</span>
                    </a>
                @endif

                @unless ($isSupervisor)
                    <a href="{{ route('patrol.scan') }}" class="{{ $linkClasses(request()->routeIs('patrol.scan')) }}" @click="sidebarOpen = false">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 5h5v5H5V5Zm9 0h5v5h-5V5ZM5 14h5v5H5v-5Zm10 1h4m-4 4h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span>Scan Checkpoint</span>
                    </a>

                    <a href="{{ route('patrol-logs.index') }}" class="{{ $linkClasses(request()->routeIs('patrol-logs.*')) }}" @click="sidebarOpen = false">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M7 3h10a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" />
                            <path d="M8.5 8h7M8.5 12h7M8.5 16h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                        </svg>
                        <span>My Patrol Logs</span>
                    </a>
                @endunless
            </div>

            @if ($isSupervisor)
                <div class="mt-6 space-y-3">
                    <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Management</p>

                    <a href="{{ route('guards.index') }}" class="{{ $linkClasses(request()->routeIs('guards.*')) }}" @click="sidebarOpen = false">
                        {!! $navIcon('users') !!}
                        <span>Guard Profiles</span>
                    </a>

                    <a href="{{ route('checkpoints.index') }}" class="{{ $linkClasses(request()->routeIs('checkpoints.*')) }}" @click="sidebarOpen = false">
                        {!! $navIcon('checkpoints') !!}
                        <span>Checkpoints</span>
                    </a>

                    @foreach ($managementSections as $section)
                    <div x-data="{ expanded: {{ $section['active'] ? 'true' : 'false' }} }" class="rounded-lg">
                        <button
                            type="button"
                            class="{{ $sectionButtonClasses }}"
                            @click="expanded = ! expanded"
                            :aria-expanded="expanded"
                        >
                            <span class="flex items-center gap-3">
                                {!! $navIcon($section['icon']) !!}
                                {{ $section['label'] }}
                            </span>
                            <svg class="h-4 w-4" :class="{ 'rotate-180': expanded }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div x-show="expanded" style="{{ $section['active'] ? '' : 'display: none;' }}" class="mt-1 space-y-1 pl-8">
                            @foreach ($section['items'] as $item)
                                <a href="{{ $item['href'] }}" class="{{ $subLinkClasses($item['active']) }}" @click="sidebarOpen = false">
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                    @endforeach
                </div>
            @endif

            @unless ($isSupervisor)
                <div class="mt-6 space-y-1">
                    <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Account</p>
                    <a href="{{ route('profile.edit') }}" class="{{ $linkClasses(request()->routeIs('profile.edit')) }}" @click="sidebarOpen = false">
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span>Profile</span>
                    </a>
                </div>

                <div class="mt-auto pt-6">
                    <div class="rounded-md border border-blue-100 bg-blue-50/70 p-3 shadow-sm dark:border-slate-700 dark:bg-slate-800/70">
                        <div class="flex items-center justify-between gap-3">
                            <p class="min-w-0 truncate text-xs font-bold uppercase tracking-wide text-blue-800 dark:text-blue-200">{{ $guardSidebarShift }}</p>
                            <span class="shrink-0 whitespace-nowrap text-[0.7rem] font-semibold text-slate-500 dark:text-slate-400">{{ now()->timezone('Asia/Manila')->format('h:i A') }}</span>
                        </div>

                        <div class="mt-3 grid grid-cols-[2.5rem_minmax(0,1fr)_minmax(4.75rem,auto)] items-center gap-2 rounded-md border border-blue-100 bg-white p-2 dark:border-slate-700 dark:bg-slate-900">
                            <span class="inline-flex h-10 w-10 items-center justify-center overflow-hidden rounded-full bg-blue-50 ring-1 ring-blue-100 dark:bg-slate-800 dark:ring-slate-700">
                                <img src="{{ $guardSidebarPhotoUrl }}" alt="{{ $guardSidebarFullName }} profile photo" class="h-full w-full object-cover">
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-xs font-bold text-slate-900 dark:text-slate-100" title="{{ $guardSidebarFullName }}">{{ $guardSidebarDisplayName }}</p>
                                <p class="truncate text-[0.65rem] font-semibold uppercase tracking-wide text-slate-500 dark:text-slate-400">{{ $guardSidebarEmployeeNo }}</p>
                            </div>
                            <div class="min-w-0 rounded-md border border-blue-100 bg-blue-50 px-2 py-1 text-right dark:border-blue-900 dark:bg-blue-950">
                                <p class="text-[0.65rem] font-bold uppercase tracking-wide text-blue-700 dark:text-blue-200">UID</p>
                                <p class="max-w-[5rem] truncate font-mono text-[0.65rem] font-bold text-blue-950 dark:text-blue-100">{{ $guardSidebarRfid }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            @endunless

            @if ($isSupervisor)
                <div class="mt-6 space-y-3">
                    <p class="px-3 text-xs font-semibold uppercase tracking-wide text-slate-400">Monitoring</p>

                @foreach ($monitoringSections as $section)
                    <div x-data="{ expanded: {{ $section['active'] ? 'true' : 'false' }} }" class="rounded-lg">
                        <button
                            type="button"
                            class="{{ $sectionButtonClasses }}"
                            @click="expanded = ! expanded"
                            :aria-expanded="expanded"
                        >
                            <span class="flex items-center gap-3">
                                {!! $navIcon($section['icon']) !!}
                                {{ $section['label'] }}
                            </span>
                            <svg class="h-4 w-4" :class="{ 'rotate-180': expanded }" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div x-show="expanded" style="{{ $section['active'] ? '' : 'display: none;' }}" class="mt-1 space-y-1 pl-8">
                            @foreach ($section['items'] as $item)
                                <a href="{{ $item['href'] }}" class="{{ $subLinkClasses($item['active']) }}" @click="sidebarOpen = false">
                                    {{ $item['label'] }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endforeach

                @if ($isSupervisor)
                    <a href="{{ route('scan-issues.index') }}" class="{{ $linkClasses(request()->routeIs('scan-issues.*')) }}" @click="sidebarOpen = false">
                        {!! $navIcon('scan_issues') !!}
                        <span>Scan Issues</span>
                    </a>

                    <a href="{{ route('readers.index') }}" class="{{ $linkClasses(request()->routeIs('readers.*')) }}" @click="sidebarOpen = false">
                        {!! $navIcon('readers') !!}
                        <span>Reader Status</span>
                    </a>

                    <a href="{{ route('audit-logs.index') }}" class="{{ $linkClasses(request()->routeIs('audit-logs.*')) }}" @click="sidebarOpen = false">
                        {!! $navIcon('audit') !!}
                        <span>Audit Trail</span>
                    </a>

                    <a href="{{ route('reports.index') }}" class="{{ $linkClasses(request()->routeIs('reports.*')) }}" @click="sidebarOpen = false">
                        {!! $navIcon('reports') !!}
                        <span>Reports</span>
                    </a>
                @endif
                </div>
            @endif
        </div>
    </aside>
</nav>
