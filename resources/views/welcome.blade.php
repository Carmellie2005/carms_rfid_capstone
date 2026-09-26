<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

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

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=poppins:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        $systemHref = auth()->check()
            ? (auth()->user()->role === 'guard' ? route('patrol.scan') : route('dashboard'))
            : route('login');
        $pwaStartHref = Route::has('login') ? route('login') : url('/');

        $menuItems = [
            ['label' => 'System', 'href' => '#system-flow'],
            ['label' => 'Users', 'href' => '#users'],
            ['label' => 'Records', 'href' => '#records'],
            ['label' => 'Reports', 'href' => '#reports'],
        ];

        $featureItems = [
            [
                'title' => 'RFID Checkpoints',
                'body' => 'Track and verify checkpoint visits',
                'color' => 'blue',
                'icon' => 'wifi',
            ],
            [
                'title' => 'Area Selfie',
                'body' => 'Capture proof of patrol presence',
                'color' => 'green',
                'icon' => 'camera',
            ],
            [
                'title' => 'GPS Tagging',
                'body' => 'Record patrol location details',
                'color' => 'sky',
                'icon' => 'pin',
            ],
            [
                'title' => 'PDF Reports',
                'body' => 'Generate patrol and incident records',
                'color' => 'red',
                'icon' => 'file',
            ],
        ];

        $flowSteps = [
            ['title' => 'Register', 'body' => 'Supervisor creates guard accounts and RFID assignments.'],
            ['title' => 'Scan', 'body' => 'Guard taps the RFID checkpoint reader.'],
            ['title' => 'Document', 'body' => 'Guard submits area selfie, location, checklist, and photos.'],
            ['title' => 'Review', 'body' => 'Supervisor reviews logs, incidents, audit trail, and reports.'],
        ];
    @endphp
    <body x-data="pwaInstallPrompt({ appName: 'SLSU Bontoc Patrol', startUrl: @js($pwaStartHref) })" class="bg-white font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <x-pwa-launch-splash />

        <div
            x-show="installModalOpen"
            x-cloak
            x-transition.opacity.duration.150ms
            class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/55 p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="pwa-install-title"
            aria-describedby="pwa-install-message"
            @click.self="closeInstallModal()"
            @keydown.escape.window="closeInstallModal()"
        >
            <section class="w-full max-w-sm rounded-md border border-blue-100 bg-white p-5 text-center shadow-2xl dark:border-slate-800 dark:bg-slate-900">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-md bg-blue-50 text-blue-700 ring-1 ring-blue-100 dark:bg-slate-800 dark:text-blue-200 dark:ring-slate-700">
                    <svg x-show="isBusy()" class="h-7 w-7 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
                        <path class="opacity-90" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                    </svg>
                    <svg x-show="installState === 'installed'" x-cloak class="h-7 w-7 text-emerald-600 dark:text-emerald-300" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M20 6 9 17l-5-5" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                    <svg x-show="! isBusy() && installState !== 'installed'" x-cloak class="h-7 w-7" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M12 3v10m0 0 4-4m-4 4-4-4M5 15v3a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3v-3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                    </svg>
                </div>

                <h2 id="pwa-install-title" class="mt-4 text-lg font-bold text-blue-950 dark:text-white" x-text="installModalTitle()">Installing...</h2>
                <p id="pwa-install-message" class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300" x-text="installModalMessage()">Please wait while the app is installed.</p>

                <div class="mt-5 flex flex-col gap-2">
                    <button
                        type="button"
                        x-show="installState === 'installed'"
                        x-cloak
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                        @click="openApp"
                    >
                        <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M5 12h12m0 0-4-4m4 4-4 4M5 5h14v14H5V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span>Open System</span>
                    </button>

                    <button
                        type="button"
                        x-show="canDismissInstallModal()"
                        x-cloak
                        class="inline-flex h-11 items-center justify-center rounded-md border border-blue-100 bg-white px-4 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-blue-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900"
                        @click="closeInstallModal()"
                    >
                        Close
                    </button>
                </div>
            </section>
        </div>

        <div class="bg-blue-950 px-4 py-2 text-white dark:bg-slate-950">
            <div class="mx-auto flex max-w-7xl items-center justify-center gap-4 text-center text-xs font-medium sm:justify-end sm:text-sm">
                <span>SLSU Bontoc Campus Security Monitoring</span>
                <span class="hidden h-4 w-px bg-white/40 sm:block"></span>
                <span class="hidden text-blue-100 sm:inline">Service - Integrity - A Safer SLSU</span>
            </div>
        </div>

        <header class="sticky top-0 z-30 border-b border-blue-100 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
            <nav class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-4 sm:px-6 lg:px-8" aria-label="Main navigation">
                <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3">
                    <x-application-logo class="h-12 w-12 shrink-0 sm:h-14 sm:w-14" />
                    <span class="min-w-0 leading-tight">
                        <span class="block truncate text-lg font-bold text-blue-950 sm:text-2xl dark:text-white">SLSU Bontoc Patrol</span>
                        <span class="block truncate text-[0.68rem] font-semibold uppercase tracking-[0.32em] text-blue-600 dark:text-blue-300">Security Monitoring</span>
                    </span>
                </a>

                <div class="hidden items-center gap-7 lg:flex">
                    @foreach ($menuItems as $item)
                        <a href="{{ $item['href'] }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-blue-950 transition hover:text-blue-700 dark:text-slate-200 dark:hover:text-blue-300">
                            <span>{{ $item['label'] }}</span>
                            <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                <path d="m5 8 5 5 5-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </a>
                    @endforeach
                </div>

                <div class="flex items-center gap-2">
                    <x-theme-toggle />

                    @if (Route::has('login'))
                        <a href="{{ $systemHref }}" class="hidden h-11 items-center justify-center rounded-md border border-blue-100 bg-white px-4 text-sm font-bold text-blue-950 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:inline-flex dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800">
                            {{ auth()->check() ? 'Open System' : 'Sign In' }}
                        </a>
                    @endif

                    <button
                        type="button"
                        class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-blue-700 px-4 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-80 sm:px-5 dark:focus:ring-offset-slate-950"
                        @click="install"
                        :disabled="isBusy()"
                        :aria-busy="isBusy().toString()"
                    >
                        <svg class="hidden h-5 w-5 shrink-0 sm:block" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 3v10m0 0 4-4m-4 4-4-4M5 15v3a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3v-3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                        </svg>
                        <span x-text="installLabel()">Install App</span>
                    </button>
                </div>
            </nav>

            <div class="border-t border-blue-50 bg-white px-4 py-2 dark:border-slate-800 dark:bg-slate-950 lg:hidden">
                <div class="mx-auto flex max-w-7xl gap-2 overflow-x-auto">
                    @foreach ($menuItems as $item)
                        <a href="{{ $item['href'] }}" class="shrink-0 rounded-md border border-blue-100 px-3 py-1.5 text-xs font-semibold text-blue-700 dark:border-slate-700 dark:text-blue-200">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </header>

        <main id="home">
            <section class="overflow-hidden border-b border-blue-100 bg-white dark:border-slate-800 dark:bg-slate-950">
                <div class="mx-auto grid min-h-[calc(100svh-9rem)] max-w-7xl items-center gap-8 px-4 py-8 sm:px-6 sm:py-12 lg:grid-cols-[0.92fr_1.08fr] lg:gap-0 lg:px-8">
                    <div class="relative z-10 max-w-3xl py-5 lg:py-12">
                        <p class="inline-flex items-center gap-2 rounded-md bg-blue-50 px-3 py-2 text-xs font-semibold text-blue-800 ring-1 ring-blue-100 dark:bg-blue-950/50 dark:text-blue-200 dark:ring-blue-800">
                            <svg class="h-4 w-4 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 3 5 6v5c0 4.5 2.9 8.6 7 10 4.1-1.4 7-5.5 7-10V6l-7-3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                <path d="m9 12 2 2 4-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            Southern Leyte State University - Bontoc Campus
                        </p>

                        <h1 class="mt-7 max-w-3xl text-4xl font-bold leading-[1.08] tracking-normal text-blue-950 sm:text-5xl lg:text-[4.55rem] dark:text-white">
                            Secure Campus Patrol and Incident Reporting
                        </h1>

                        <p class="mt-6 max-w-2xl text-base leading-8 text-slate-600 sm:text-lg dark:text-slate-300">
                            RFID checkpoint monitoring, area selfie proof, patrol logs, and incident reports in one system.
                        </p>

                        <div class="mt-8 flex flex-col gap-3 sm:flex-row">
                            <a href="{{ $systemHref }}" class="inline-flex h-12 items-center justify-center gap-2 rounded-md bg-blue-700 px-6 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5 12h12m0 0-4-4m4 4-4 4M5 5h14v14H5V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span>{{ auth()->check() ? 'Open System' : 'Open System' }}</span>
                            </a>

                            <a href="#system-flow" class="inline-flex h-12 items-center justify-center gap-2 rounded-md border border-blue-100 bg-white px-6 text-sm font-bold text-blue-950 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950">
                                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M10 8.5v7l5.5-3.5L10 8.5Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                    <path d="M12 21a9 9 0 1 0 0-18 9 9 0 0 0 0 18Z" stroke="currentColor" stroke-width="2" />
                                </svg>
                                <span>View System Flow</span>
                            </a>
                        </div>

                        <p
                            x-cloak
                            x-show="message"
                            x-text="message"
                            class="mt-4 rounded-md border border-blue-100 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-800 dark:border-blue-400/30 dark:bg-blue-950/40 dark:text-blue-100"
                        ></p>

                        <div class="mt-10 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            @foreach ($featureItems as $item)
                                <article class="border-blue-100 pr-4 sm:border-r last:border-r-0 dark:border-slate-800">
                                    <div @class([
                                        'flex h-12 w-12 items-center justify-center rounded-md',
                                        'bg-blue-50 text-blue-700' => $item['color'] === 'blue',
                                        'bg-emerald-50 text-emerald-700' => $item['color'] === 'green',
                                        'bg-sky-50 text-sky-700' => $item['color'] === 'sky',
                                        'bg-red-50 text-red-700' => $item['color'] === 'red',
                                        'dark:bg-slate-900',
                                    ])>
                                        @if ($item['icon'] === 'wifi')
                                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19h.01M2 9a14.5 14.5 0 0 1 20 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            </svg>
                                        @elseif ($item['icon'] === 'camera')
                                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M8 7h1.5L11 5h2l1.5 2H16a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3v-6a3 3 0 0 1 3-3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                                <path d="M12 16a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" />
                                            </svg>
                                        @elseif ($item['icon'] === 'pin')
                                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M12 21s6-5.2 6-11a6 6 0 1 0-12 0c0 5.8 6 11 6 11Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                                <path d="M12 12.5a2.5 2.5 0 1 0 0-5 2.5 2.5 0 0 0 0 5Z" stroke="currentColor" stroke-width="2" />
                                            </svg>
                                        @else
                                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                                <path d="M14 3v5h5M9 14h6M9 17h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                            </svg>
                                        @endif
                                    </div>
                                    <h3 class="mt-3 text-sm font-bold text-blue-950 dark:text-white">{{ $item['title'] }}</h3>
                                    <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ $item['body'] }}</p>
                                </article>
                            @endforeach
                        </div>
                    </div>

                    <div class="relative min-h-[28rem] lg:min-h-[43rem]">
                        <div class="absolute inset-0 hidden bg-blue-950 lg:block" style="clip-path: polygon(24% 0, 100% 0, 100% 100%, 3% 100%);"></div>

                        <div class="relative h-full min-h-[28rem] overflow-hidden rounded-md border border-blue-100 bg-blue-50 shadow-sm lg:absolute lg:inset-y-0 lg:right-0 lg:w-[112%] lg:rounded-none lg:border-0 lg:shadow-none dark:border-slate-800 dark:bg-slate-900" style="clip-path: polygon(0 0, 100% 0, 100% 100%, 0 100%);">
                            <img
                                src="{{ asset('images/homepage-hero-background.png') }}"
                                alt="SLSU Bontoc Campus administration building"
                                class="h-full min-h-[28rem] w-full object-cover object-center brightness-105 contrast-105 saturate-110 lg:min-h-[43rem]"
                            >
                        </div>

                        <div class="absolute left-4 top-6 w-[16rem] rounded-md border border-blue-100 bg-white/95 p-4 shadow-xl shadow-blue-950/10 backdrop-blur sm:left-14 lg:left-16 lg:top-28 dark:border-slate-700 dark:bg-slate-900/95">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-md bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-200">
                                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M5 12.5a10 10 0 0 1 14 0M8.5 16a5 5 0 0 1 7 0M12 19h.01M2 9a14.5 14.5 0 0 1 20 0" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <p class="text-sm font-bold text-blue-950 dark:text-white">RFID Scan</p>
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-[0.68rem] font-bold text-emerald-700 ring-1 ring-emerald-100 dark:bg-emerald-950 dark:text-emerald-200 dark:ring-emerald-800">Active</span>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Checkpoint monitoring</p>
                                </div>
                            </div>
                        </div>

                        <div class="absolute right-3 top-40 w-[17rem] rounded-md border border-blue-100 bg-white/95 p-4 shadow-xl shadow-blue-950/10 backdrop-blur sm:right-8 lg:right-5 lg:top-64 dark:border-slate-700 dark:bg-slate-900/95">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-md bg-emerald-50 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-200">
                                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M8 7h1.5L11 5h2l1.5 2H16a3 3 0 0 1 3 3v6a3 3 0 0 1-3 3H8a3 3 0 0 1-3-3v-6a3 3 0 0 1 3-3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                        <path d="M12 16a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-blue-950 dark:text-white">Patrol Logs</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Area selfie and checklist proof</p>
                                </div>
                            </div>
                        </div>

                        <div class="absolute bottom-8 left-7 w-[18rem] rounded-md border border-blue-100 bg-white/95 p-4 shadow-xl shadow-blue-950/10 backdrop-blur sm:left-24 lg:bottom-20 lg:left-28 dark:border-slate-700 dark:bg-slate-900/95">
                            <div class="flex items-center gap-3">
                                <div class="flex h-12 w-12 items-center justify-center rounded-md bg-red-50 text-red-700 dark:bg-red-950 dark:text-red-200">
                                    <svg class="h-7 w-7" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                        <path d="M14 3v5h5M12 11v4M12 18h.01" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-sm font-bold text-blue-950 dark:text-white">Incident Report</p>
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Photo evidence and action taken</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section id="system-flow" class="border-b border-blue-100 bg-slate-50 dark:border-slate-800 dark:bg-slate-900">
                <div class="mx-auto grid max-w-7xl gap-5 px-4 py-8 sm:px-6 lg:grid-cols-4 lg:px-8">
                    @foreach ($flowSteps as $step)
                        <article class="rounded-md border border-blue-100 bg-white p-5 dark:border-slate-800 dark:bg-slate-950">
                            <h2 class="text-base font-bold text-blue-950 dark:text-white">{{ $step['title'] }}</h2>
                            <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $step['body'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section id="users" class="border-b border-blue-100 bg-white dark:border-slate-800 dark:bg-slate-950">
                <div class="mx-auto grid max-w-7xl gap-4 px-4 py-8 sm:px-6 lg:grid-cols-3 lg:px-8">
                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-md bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-200">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M4 21h16M6 21V8l6-4 6 4v13M9 21v-7h6v7" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">Southern Leyte State University</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Bontoc Campus</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <div class="flex h-11 w-11 items-center justify-center rounded-md bg-blue-50 text-blue-700 dark:bg-blue-950 dark:text-blue-200">
                            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 3 5 6v5c0 4.5 2.9 8.6 7 10 4.1-1.4 7-5.5 7-10V6l-7-3Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                <path d="m9 12 2 2 4-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-blue-950 dark:text-white">Campus Security Monitoring</p>
                            <p class="text-sm text-slate-500 dark:text-slate-400">Record - Verify - Report - Safer Campus</p>
                        </div>
                    </div>

                    <div id="records" class="flex items-center gap-3 lg:justify-end">
                        <div class="text-left lg:text-right">
                            <p id="reports" class="text-sm font-semibold text-slate-500 dark:text-slate-400">RFID Checkpoints - Patrol Logs - Incident Reports</p>
                            <p id="install-app" class="text-sm font-bold text-blue-950 dark:text-white">Built for supervisor and guard access</p>
                        </div>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-blue-100 bg-white dark:border-slate-800 dark:bg-slate-950">
            <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-6 text-sm text-slate-500 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8 dark:text-slate-400">
                <p class="font-semibold text-blue-950 dark:text-white">SLSU Bontoc Patrol</p>
                <p>{{ now()->year }} Southern Leyte State University - Bontoc Campus</p>
            </div>
        </footer>
    </body>
</html>
