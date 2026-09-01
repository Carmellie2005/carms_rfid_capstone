<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>SLSU Bontoc Patrol</title>
        <x-favicon />

        <script>
            (() => {
                const theme = localStorage.getItem('theme');
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                if (theme === 'dark' || (! theme && prefersDark)) {
                    document.documentElement.classList.add('dark');
                } else {
                    document.documentElement.classList.remove('dark');
                }
            })();
        </script>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-full overflow-x-hidden font-sans antialiased lg:h-full lg:overflow-hidden">
        <x-pwa-launch-splash />

        @php
            $user = Auth::user();
            $role = $user->role ?? 'admin';
            $isSupervisor = $role === 'admin';
            $roleLabel = $role === 'admin' ? 'Supervisor' : ucfirst($role);
            $today = now()->timezone('Asia/Manila')->format('l, F d, Y');
            $mobileToday = now()->timezone('Asia/Manila')->format('M d, Y');
            $currentTime = now()->timezone('Asia/Manila')->format('h:i:s A');
            $currentShortTime = now()->timezone('Asia/Manila')->format('h:i A');
            $accountIconPath = $isSupervisor
                ? 'images/user-icons/supervisor-account.png'
                : 'images/user-icons/guard-account.png';
            $accountPhotoUrl = $user?->profile_photo_path
                ? asset('storage/'.$user->profile_photo_path)
                : asset($accountIconPath);
            $todayDate = now('Asia/Manila')->toDateString();
            $guardProfileId = $isSupervisor ? null : $user?->guardProfile?->id;

            $notificationIncidentQuery = \App\Models\IncidentReport::with(['securityGuard', 'checkpoint'])
                ->whereIn('status', ['submitted', 'under_review'])
                ->when(! $isSupervisor, fn ($query) => $query->where('guard_id', $guardProfileId ?? 0))
                ->whereDoesntHave('notificationReads', fn ($query) => $query->where('user_id', $user?->id));

            $notificationPatrolQuery = \App\Models\PatrolLog::with(['securityGuard', 'checkpoint'])
                ->whereIn('status', ['suspicious', 'invalid', 'pending_face', 'profile_incomplete', 'outside_schedule'])
                ->whereDate('scanned_at', $todayDate)
                ->when(! $isSupervisor, fn ($query) => $query->where('guard_id', $guardProfileId ?? 0))
                ->whereDoesntHave('notificationReads', fn ($query) => $query->where('user_id', $user?->id));

            $notificationCount = (clone $notificationIncidentQuery)->count() + (clone $notificationPatrolQuery)->count();

            $incidentNotifications = (clone $notificationIncidentQuery)
                ->latest('incident_at')
                ->take(4)
                ->get()
                ->map(fn ($incident) => [
                    'read_type' => 'incident',
                    'read_id' => $incident->id,
                    'type' => 'Incident',
                    'title' => $incident->category ?: 'Incident report',
                    'body' => collect([$incident->securityGuard?->name, $incident->checkpoint?->name])->filter()->implode(' - ') ?: 'Incident report submitted',
                    'time' => $incident->incident_at,
                    'badge' => ucfirst($incident->priority ?: 'Normal'),
                    'href' => $isSupervisor
                        ? route('incidents.index', ['status' => $incident->status])
                        : route('patrol-logs.index', ['date' => $incident->incident_at?->toDateString()]),
                ]);

            $patrolNotifications = (clone $notificationPatrolQuery)
                ->latest('scanned_at')
                ->take(4)
                ->get()
                ->map(fn ($patrol) => [
                    'read_type' => 'patrol',
                    'read_id' => $patrol->id,
                    'type' => 'Patrol',
                    'title' => \Illuminate\Support\Str::of($patrol->status)->replace('_', ' ')->title()->toString().' scan',
                    'body' => collect([$patrol->securityGuard?->name, $patrol->checkpoint?->name])->filter()->implode(' - ') ?: 'Checkpoint scan needs review',
                    'time' => $patrol->scanned_at,
                    'badge' => \Illuminate\Support\Str::of($patrol->status)->replace('_', ' ')->title()->toString(),
                    'href' => route('patrol-logs.index', ['status' => $patrol->status, 'date' => $patrol->scanned_at?->toDateString()]),
                ]);

            $notificationItems = $incidentNotifications
                ->concat($patrolNotifications)
                ->sortByDesc(fn ($item) => $item['time']?->timestamp ?? 0)
                ->take(6)
                ->values();
        @endphp

        <div
            x-data="{
                sidebarOpen: false,
                sidebarCollapsed: false,
                pageBusy: false,
                markPageBusy(event) {
                    if (event.defaultPrevented || event.target?.dataset?.skipGlobalLoader === 'true') {
                        return;
                    }

                    this.pageBusy = true;
                },
                shouldSkipGlobalLoaderForLink(link) {
                    return link.target === '_blank'
                        || link.hasAttribute('download')
                        || link.dataset.skipGlobalLoader === 'true';
                },
                isDownloadLikeUrl(url) {
                    const path = url.pathname.toLowerCase().replace(/\/+$/, '');

                    return path.endsWith('/pdf') || path.endsWith('.pdf');
                },
                handlePageClick(event) {
                    if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                        return;
                    }

                    const link = event.target.closest('a[href]');

                    if (! link || this.shouldSkipGlobalLoaderForLink(link)) {
                        return;
                    }

                    const href = link.getAttribute('href') || '';

                    if (href === '' || href.startsWith('#') || href.startsWith('javascript:')) {
                        return;
                    }

                    const url = new URL(link.href, window.location.href);

                    if (url.origin !== window.location.origin) {
                        return;
                    }

                    if (this.isDownloadLikeUrl(url)) {
                        return;
                    }

                    if (url.pathname === window.location.pathname && url.search === window.location.search && url.hash) {
                        return;
                    }

                    this.pageBusy = true;
                },
            }"
            x-on:submit="markPageBusy($event)"
            x-on:click="handlePageClick($event)"
            class="app-viewport bg-blue-50/60 dark:bg-slate-950 lg:overflow-hidden"
        >
            <div
                x-show="pageBusy"
                x-cloak
                x-transition.opacity.duration.150ms
                class="fixed inset-0 z-[90] flex items-center justify-center bg-white/75 p-4 backdrop-blur-sm dark:bg-slate-950/75"
            >
                <x-brand-spinner class="rounded-lg border border-blue-100 bg-white/95 p-6 text-blue-950 shadow-2xl dark:border-slate-800 dark:bg-slate-900/95 dark:text-blue-100">
                    Loading
                    <x-slot name="description">Please wait a moment.</x-slot>
                </x-brand-spinner>
            </div>

            @include('layouts.navigation')

            <div
                class="app-content-shell flex min-h-0 flex-col lg:h-full lg:pl-72"
                :class="sidebarCollapsed ? 'lg:!pl-16' : 'lg:!pl-72'"
            >
                <header class="sticky top-0 z-30 shrink-0 border-b border-blue-100 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
                    <div class="mx-auto flex min-h-[4rem] max-w-7xl items-center justify-between gap-2 py-2 pl-16 pr-3 sm:gap-4 sm:px-6 lg:px-8">
                        <div
                            class="flex min-w-0 items-center gap-3"
                            x-data="{
                                timeNow: @js($currentTime),
                                shortTimeNow: @js($currentShortTime),
                                updateTime() {
                                    const currentDate = new Date();

                                    this.timeNow = new Intl.DateTimeFormat('en-US', {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        second: '2-digit',
                                        hour12: true,
                                        timeZone: 'Asia/Manila'
                                    }).format(currentDate);
                                    this.shortTimeNow = new Intl.DateTimeFormat('en-US', {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                        hour12: true,
                                        timeZone: 'Asia/Manila'
                                    }).format(currentDate);
                                },
                                init() {
                                    this.updateTime();
                                    setInterval(() => this.updateTime(), 1000);
                                }
                            }"
                        >
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-400">Today</p>
                                <p class="truncate text-xs font-semibold text-blue-950 dark:text-blue-100 sm:text-base">
                                    <span class="sm:hidden">{{ $mobileToday }}</span>
                                    <span class="hidden sm:inline">{{ $today }}</span>
                                    <span class="text-slate-400">|</span>
                                    <span class="font-mono sm:hidden" x-text="shortTimeNow">{{ $currentShortTime }}</span>
                                    <span class="hidden font-mono sm:inline" x-text="timeNow">{{ $currentTime }}</span>
                                </p>
                            </div>
                        </div>

                        <div class="flex shrink-0 items-center gap-2 sm:gap-3">
                            <div class="relative" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                                <div>
                                    <button
                                        type="button"
                                        class="relative inline-flex h-11 w-11 items-center justify-center rounded-lg border border-blue-100 bg-white text-slate-700 shadow-sm transition hover:bg-blue-50 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-blue-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950"
                                        aria-label="Open notifications"
                                        x-on:click.stop="open = ! open"
                                    >
                                        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                            <path d="M15 17H9m9-6a6 6 0 0 0-12 0c0 3-1 4.5-2 6h16c-1-1.5-2-3-2-6Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                            <path d="M10 20a2 2 0 0 0 4 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                        </svg>
                                        @if ($notificationCount > 0)
                                            <span class="absolute -right-1 -top-1 inline-flex min-w-5 items-center justify-center rounded-full bg-red-600 px-1.5 py-0.5 text-[11px] font-bold leading-none text-white ring-2 ring-white dark:ring-slate-900">
                                                {{ $notificationCount > 99 ? '99+' : $notificationCount }}
                                            </span>
                                        @endif
                                    </button>
                                </div>

                                <div
                                    x-show="open"
                                    x-cloak
                                    x-transition.opacity.duration.150ms
                                    class="fixed inset-0 z-[60] bg-slate-950/45 sm:hidden"
                                    x-on:click="open = false"
                                    aria-hidden="true"
                                ></div>

                                <section
                                    x-show="open"
                                    x-cloak
                                    x-transition:enter="transition ease-out duration-200"
                                    x-transition:enter-start="opacity-0 sm:scale-95"
                                    x-transition:enter-end="opacity-100 sm:scale-100"
                                    x-transition:leave="transition ease-in duration-100"
                                    x-transition:leave-start="opacity-100 sm:scale-100"
                                    x-transition:leave-end="opacity-0 sm:scale-95"
                                    class="fixed inset-x-3 top-20 z-[70] flex max-h-[min(78dvh,32rem)] flex-col overflow-hidden rounded-lg bg-white shadow-2xl ring-1 ring-black/5 dark:bg-slate-900 sm:absolute sm:inset-auto sm:end-0 sm:top-full sm:mt-2 sm:h-auto sm:max-h-[calc(100vh-5rem)] sm:w-96 sm:rounded-md sm:ring-black sm:ring-opacity-5"
                                    x-on:click.outside="open = false"
                                    aria-label="Notifications"
                                >
                                    <div class="shrink-0 border-b border-blue-100 px-3 py-3 dark:border-slate-800 sm:px-4">
                                        <div class="flex items-start justify-between gap-3">
                                            <div>
                                                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">Notifications</p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ $notificationCount }} unread {{ \Illuminate\Support\Str::plural('alert', $notificationCount) }}
                                                </p>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                @if ($notificationCount > 0)
                                                    <form method="POST" action="{{ route('notifications.read-all') }}">
                                                        @csrf
                                                        <button type="submit" class="inline-flex h-8 items-center justify-center rounded-md border border-blue-200 px-2.5 text-xs font-semibold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:text-blue-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900">
                                                            Mark all read
                                                        </button>
                                                    </form>
                                                @endif
                                                <button type="button" class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-blue-100 text-slate-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900 sm:hidden" x-on:click="open = false" aria-label="Close notifications">
                                                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                        <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mobile-scroll-area min-h-0 flex-1 overflow-y-auto border-b border-blue-100 dark:border-slate-800">
                                        @forelse ($notificationItems as $item)
                                            <div class="border-b border-blue-50 last:border-b-0 dark:border-slate-800">
                                                <div class="flex items-start gap-3 px-3 py-2.5 transition hover:bg-blue-50 dark:hover:bg-slate-800 sm:px-4 sm:py-3">
                                                    <a href="{{ $item['href'] }}" class="min-w-0 flex-1 focus:outline-none">
                                                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">{{ $item['type'] }}</p>
                                                        <p class="mt-1 text-sm font-semibold text-slate-900 dark:text-slate-100">{{ $item['title'] }}</p>
                                                        <p class="mt-1 line-clamp-2 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $item['body'] }}</p>
                                                        @if ($item['time'])
                                                            <p class="mt-2 text-xs font-medium text-slate-400">{{ $item['time']->diffForHumans() }}</p>
                                                        @endif
                                                    </a>
                                                    <div class="flex shrink-0 flex-col items-end gap-2">
                                                        <span class="rounded-full bg-blue-50 px-2 py-1 text-xs font-semibold text-blue-700 ring-1 ring-blue-200 dark:bg-blue-950 dark:text-blue-200 dark:ring-blue-800">
                                                            {{ $item['badge'] }}
                                                        </span>
                                                        <form method="POST" action="{{ route('notifications.read') }}">
                                                            @csrf
                                                            <input type="hidden" name="type" value="{{ $item['read_type'] }}">
                                                            <input type="hidden" name="id" value="{{ $item['read_id'] }}">
                                                            <button type="submit" class="text-xs font-semibold text-slate-500 underline underline-offset-2 transition hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:text-slate-400 dark:hover:text-blue-200 dark:focus:ring-offset-slate-900">
                                                                Mark as read
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="flex items-center justify-center px-4 py-8 text-center">
                                                <div>
                                                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">No alerts right now</p>
                                                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">New incident reports and patrol alerts will appear here.</p>
                                                </div>
                                            </div>
                                        @endforelse
                                    </div>

                                    <div class="shrink-0 px-3 py-3 sm:px-4">
                                        @if ($isSupervisor)
                                            <a href="{{ route('incidents.index') }}" class="inline-flex w-full items-center justify-center rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                                View Incident Reports
                                            </a>
                                        @else
                                            <a href="{{ route('patrol-logs.index') }}" class="inline-flex w-full items-center justify-center rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                                View My Patrol Logs
                                            </a>
                                        @endif
                                    </div>
                                </section>
                            </div>

                            <x-theme-toggle />

                            <x-dropdown align="right" width="56" contentClasses="bg-white py-2 dark:bg-slate-900">
                                <x-slot name="trigger">
                                    <button
                                        type="button"
                                        class="inline-flex h-11 w-11 items-center justify-center rounded-lg border border-blue-100 bg-white text-slate-700 shadow-sm transition hover:bg-blue-50 hover:text-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-blue-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950"
                                        aria-label="Open profile menu"
                                    >
                                        <span class="inline-flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-blue-50 ring-1 ring-blue-100 dark:bg-slate-800 dark:ring-slate-700">
                                            <img
                                                src="{{ $accountPhotoUrl }}"
                                                alt="{{ $roleLabel }} profile photo"
                                                class="h-full w-full object-cover"
                                            >
                                        </span>
                                    </button>
                                </x-slot>

                                <x-slot name="content">
                                    <div class="flex items-center gap-3 border-b border-blue-100 px-4 py-3 dark:border-slate-800">
                                        <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full bg-blue-50 ring-1 ring-blue-100 dark:bg-slate-800 dark:ring-slate-700">
                                            <img
                                                src="{{ $accountPhotoUrl }}"
                                                alt="{{ $roleLabel }} profile photo"
                                                class="h-full w-full object-cover"
                                            >
                                        </span>
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-slate-900 dark:text-slate-100">{{ Auth::user()->name }}</p>
                                            <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ Auth::user()->email }}</p>
                                        </div>
                                    </div>

                                    <x-dropdown-link :href="route('profile.edit')">
                                        Profile Settings
                                    </x-dropdown-link>

                                    <form method="POST" action="{{ route('logout') }}">
                                        @csrf
                                        <x-dropdown-link :href="route('logout')" onclick="event.preventDefault(); this.closest('form').submit();">
                                            Log Out
                                        </x-dropdown-link>
                                    </form>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    </div>
                </header>

                <!-- Page Heading -->
                @if (isset($header))
                    <header class="shrink-0 border-b border-blue-100 bg-white dark:border-slate-800 dark:bg-slate-900">
                        <div class="mx-auto max-w-7xl px-4 py-5 sm:px-6 lg:px-8">
                            {{ $header }}
                        </div>
                    </header>
                @endif

                <!-- Page Content -->
                <main class="app-scroll-main mobile-scroll-area min-w-0 flex-1">
                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
