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
            $accountFallbackPhotoUrl = asset($accountIconPath);
            $accountPhotoUrl = $user?->profile_photo_path
                && \Illuminate\Support\Facades\Storage::disk('public')->exists($user->profile_photo_path)
                ? asset('storage/'.$user->profile_photo_path)
                : $accountFallbackPhotoUrl;
            $notificationCount = \App\Support\NotificationFeed::unreadCountFor($user);
            $notificationPreviewLimit = \App\Support\NotificationFeed::DROPDOWN_LIMIT;
            $notificationItems = \App\Support\NotificationFeed::unreadItemsFor($user, $notificationPreviewLimit);
        @endphp

        <div
            x-data="{
                sidebarOpen: false,
                sidebarCollapsed: false,
                notificationsOpen: false,
                pageBusy: false,
                markPageBusy(event) {
                    if (event.defaultPrevented || event.target?.dataset?.skipGlobalLoader === 'true') {
                        return;
                    }

                    const form = event.target;
                    const method = (form?.getAttribute('method') || 'GET').toUpperCase();

                    if (method === 'GET') {
                        return;
                    }

                    this.pageBusy = true;
                },
            }"
            x-on:submit="markPageBusy($event)"
            x-on:open-notifications-modal.window="notificationsOpen = true"
            x-on:keydown.escape.window="notificationsOpen = false"
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
                                <p class="truncate text-xs font-semibold text-blue-950 dark:text-blue-100 lg:text-base">
                                    <span class="lg:hidden">{{ $mobileToday }}</span>
                                    <span class="hidden lg:inline">{{ $today }}</span>
                                    <span class="text-slate-400">|</span>
                                    <span class="font-mono lg:hidden" x-text="shortTimeNow">{{ $currentShortTime }}</span>
                                    <span class="hidden font-mono lg:inline" x-text="timeNow">{{ $currentTime }}</span>
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
                                                    @if ($notificationCount > $notificationPreviewLimit)
                                                        Latest {{ $notificationPreviewLimit }} of {{ $notificationCount }} unread alerts
                                                    @else
                                                        {{ $notificationCount }} unread {{ \Illuminate\Support\Str::plural('alert', $notificationCount) }}
                                                    @endif
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
                                                        <p class="mt-1 max-h-10 overflow-hidden text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $item['body'] }}</p>
                                                        @if ($item['time_label'] !== 'Not recorded')
                                                            <p class="mt-2 text-xs font-medium text-slate-400">
                                                                {{ $item['time_label'] }}
                                                                @if ($item['relative_time'])
                                                                    <span class="text-slate-300 dark:text-slate-600">|</span>
                                                                    {{ $item['relative_time'] }}
                                                                @endif
                                                            </p>
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
                                        <a href="{{ route('notifications.index') }}" data-skip-global-loader="true" x-on:click.prevent="open = false; $dispatch('open-notifications-modal')" class="inline-flex w-full items-center justify-center rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                                            View all notifications
                                        </a>
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
                                                onerror="this.onerror=null; this.src='{{ $accountFallbackPhotoUrl }}';"
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
                                                onerror="this.onerror=null; this.src='{{ $accountFallbackPhotoUrl }}';"
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

            <div
                x-show="notificationsOpen"
                x-cloak
                x-transition.opacity.duration.150ms
                class="fixed inset-0 z-[80] bg-slate-950/45"
                x-on:click="notificationsOpen = false"
                aria-hidden="true"
            ></div>

            <section
                x-show="notificationsOpen"
                x-cloak
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 translate-y-3 sm:translate-x-4 sm:translate-y-0"
                x-transition:enter-end="opacity-100 translate-y-0 sm:translate-x-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 translate-y-0 sm:translate-x-0"
                x-transition:leave-end="opacity-0 translate-y-3 sm:translate-x-4 sm:translate-y-0"
                class="fixed inset-0 z-[85] flex flex-col bg-white shadow-2xl dark:bg-slate-900 sm:inset-y-4 sm:left-auto sm:right-4 sm:w-[min(34rem,calc(100vw-2rem))] sm:rounded-md"
                role="dialog"
                aria-modal="true"
                aria-label="All notifications"
            >
                <div class="flex shrink-0 items-center justify-between gap-3 border-b border-blue-100 px-4 py-3 dark:border-slate-800">
                    <div class="min-w-0">
                        <h2 class="truncate text-base font-semibold text-blue-950 dark:text-blue-100">All Notifications</h2>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $notificationCount }} unread {{ \Illuminate\Support\Str::plural('alert', $notificationCount) }}</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-2">
                        <a href="{{ route('notifications.index') }}" class="hidden whitespace-nowrap text-xs font-semibold text-blue-700 hover:text-blue-900 dark:text-blue-200 dark:hover:text-blue-100 sm:inline-flex">
                            Open page
                        </a>
                        <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-md border border-blue-100 text-slate-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900" x-on:click="notificationsOpen = false" aria-label="Close notifications modal">
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                            </svg>
                        </button>
                    </div>
                </div>
                <iframe
                    x-bind:src="notificationsOpen ? '{{ route('notifications.index', ['embedded' => 1]) }}' : 'about:blank'"
                    title="All notifications"
                    class="min-h-0 flex-1 border-0 bg-blue-50/60 dark:bg-slate-950"
                ></iframe>
            </section>
        </div>
    </body>
</html>
