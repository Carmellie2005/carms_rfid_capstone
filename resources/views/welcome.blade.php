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
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700,800&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>

    @php
        $systemHref = auth()->check()
            ? (auth()->user()->role === 'guard' ? route('patrol.scan') : route('dashboard'))
            : route('login');

        $pwaStartHref = Route::has('login') ? route('login') : url('/');

        $flowSteps = [
            ['label' => 'Register', 'title' => 'Guard and RFID setup', 'body' => 'Supervisor creates guard accounts, assigns RFID UIDs, and keeps checkpoint readers active.'],
            ['label' => 'Scan', 'title' => 'Checkpoint visit', 'body' => 'Guard taps the RFID card at a checkpoint reader to start a timestamped patrol record.'],
            ['label' => 'Verify', 'title' => 'Area selfie and checklist', 'body' => 'The patrol record is supported by area selfie proof, location tagging, checklist answers, and photos.'],
            ['label' => 'Review', 'title' => 'Reports and monitoring', 'body' => 'Supervisor reviews patrol logs, incidents, scan issues, audit trail, and PDF reports.'],
        ];

        $recordItems = [
            ['title' => 'RFID Checkpoints', 'body' => 'BITS, Guard House, Campus Canteen, MPC, and Tilapia Hatchery reader records.'],
            ['title' => 'Patrol Logs', 'body' => 'Guard identity, checkpoint, scan time, area selfie, GPS details, checklist status, and proof photos.'],
            ['title' => 'Incident Reports', 'body' => 'Incident category, priority, description, evidence photos, status, action taken, and resolved date.'],
            ['title' => 'Account Control', 'body' => 'Supervisor-managed guard login details, password reset, active status, and audit trail.'],
        ];
    @endphp

    <body x-data="pwaInstallPrompt({ appName: 'SLSU Bontoc Patrol', startUrl: @js($pwaStartHref) })" class="bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <x-pwa-launch-splash />

        <div
            x-show="installModalOpen"
            x-cloak
            x-transition.opacity.duration.150ms
            class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/60 p-4"
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

        <header class="sticky top-0 z-30 border-b border-blue-100 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
            <nav class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8" aria-label="Main navigation">
                <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3">
                    <x-application-logo class="h-10 w-10 shrink-0 sm:h-11 sm:w-11" />
                    <span class="min-w-0 leading-tight">
                        <span class="block truncate text-sm font-bold text-blue-950 sm:text-base dark:text-white">SLSU Bontoc Patrol</span>
                        <span class="block truncate text-xs font-medium text-blue-600 dark:text-blue-300">Campus Security Monitoring</span>
                    </span>
                </a>

                <div class="flex items-center gap-2">
                    <x-theme-toggle />

                    <button
                        type="button"
                        class="hidden h-10 items-center justify-center rounded-md border border-blue-100 bg-white px-3 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-blue-200 dark:hover:bg-slate-800 sm:inline-flex"
                        @click="install"
                        :disabled="isBusy()"
                    >
                        <span x-text="installLabel()">Install App</span>
                    </button>

                    @if (Route::has('login'))
                        <a href="{{ $systemHref }}" class="inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:px-4 dark:focus:ring-offset-slate-950">
                            {{ auth()->check() ? 'Open System' : 'Log in' }}
                        </a>
                    @endif
                </div>
            </nav>
        </header>

        <main>
            <section class="relative isolate overflow-hidden border-b border-blue-100 bg-slate-900 dark:border-slate-800">
                <img
                    src="{{ asset('images/homepage-hero-background.png') }}"
                    alt=""
                    class="absolute inset-0 -z-20 h-full w-full object-cover"
                    aria-hidden="true"
                >
                <div class="absolute inset-0 -z-10 bg-slate-950/62 dark:bg-slate-950/76"></div>

                <div class="mx-auto grid min-h-[calc(100svh-9rem)] max-w-7xl items-center gap-8 px-4 py-12 sm:px-6 sm:py-16 lg:grid-cols-[1.1fr_0.9fr] lg:px-8">
                    <div class="max-w-3xl">
                        <p class="inline-flex rounded-md border border-white/35 bg-white/15 px-3 py-1.5 text-[0.68rem] font-bold uppercase tracking-wide text-blue-50 backdrop-blur">
                            Southern Leyte State University - Bontoc Campus
                        </p>

                        <h1 class="mt-4 text-4xl font-extrabold leading-tight tracking-normal text-white sm:text-5xl lg:text-6xl">
                            SLSU Bontoc Patrol
                        </h1>

                        <p class="mt-4 max-w-2xl text-base font-medium leading-7 text-blue-50 sm:text-lg">
                            A web-based RFID patrol and incident reporting system for campus security operations.
                        </p>

                        <div class="mt-6 flex flex-col gap-2.5 sm:flex-row">
                            <a href="{{ $systemHref }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-blue-700 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-slate-950/25 transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 focus:ring-offset-slate-950">
                                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5 12h12m0 0-4-4m4 4-4 4M5 5h14v14H5V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span>{{ auth()->check() ? 'Open System' : 'Log in to System' }}</span>
                            </a>

                            <button
                                type="button"
                                class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-white px-5 py-3 text-sm font-bold text-blue-950 shadow-lg shadow-slate-950/25 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 focus:ring-offset-slate-950 disabled:cursor-wait disabled:opacity-80"
                                @click="install"
                                :disabled="isBusy()"
                                :aria-busy="isBusy().toString()"
                            >
                                <svg x-show="! isBusy() && installLabel() !== 'Open App'" class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M12 3v10m0 0 4-4m-4 4-4-4M5 15v3a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3v-3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <svg x-show="isBusy()" x-cloak class="h-5 w-5 shrink-0 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
                                    <path class="opacity-90" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
                                </svg>
                                <span x-text="installLabel()">Install App</span>
                            </button>
                        </div>

                        <p
                            x-cloak
                            x-show="message"
                            x-text="message"
                            class="mt-3 max-w-xl rounded-md bg-slate-950/50 px-4 py-2 text-sm font-medium text-blue-50"
                        ></p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-3 lg:grid-cols-1">
                        <div class="rounded-md border border-white/25 bg-white/90 p-4 shadow-xl shadow-slate-950/20 backdrop-blur dark:border-slate-700 dark:bg-slate-900/90">
                            <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">Main Purpose</p>
                            <p class="mt-2 text-sm font-semibold leading-6 text-slate-800 dark:text-slate-100">Record checkpoint visits, collect proof, and support supervisor review.</p>
                        </div>
                        <div class="rounded-md border border-white/25 bg-white/90 p-4 shadow-xl shadow-slate-950/20 backdrop-blur dark:border-slate-700 dark:bg-slate-900/90">
                            <p class="text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Patrol Evidence</p>
                            <p class="mt-2 text-sm font-semibold leading-6 text-slate-800 dark:text-slate-100">RFID scan, area selfie, GPS details, checklist status, and incident photos.</p>
                        </div>
                        <div class="rounded-md border border-white/25 bg-white/90 p-4 shadow-xl shadow-slate-950/20 backdrop-blur dark:border-slate-700 dark:bg-slate-900/90">
                            <p class="text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">Users</p>
                            <p class="mt-2 text-sm font-semibold leading-6 text-slate-800 dark:text-slate-100">Supervisor manages records. Guards complete checkpoint patrol documentation.</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="border-b border-blue-100 bg-white dark:border-slate-800 dark:bg-slate-950">
                <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                        <div class="max-w-2xl">
                            <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">System Flow</p>
                            <h2 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl dark:text-white">How patrol records are created</h2>
                        </div>
                        <p class="max-w-2xl text-sm leading-6 text-slate-600 dark:text-slate-300">
                            The process starts with guard setup and ends with patrol and incident records that supervisors can review.
                        </p>
                    </div>

                    <div class="mt-7 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                        @foreach ($flowSteps as $index => $step)
                            <article class="rounded-md border border-blue-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900">
                                <div class="flex items-center gap-3">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-blue-700 text-sm font-bold text-white">{{ $index + 1 }}</span>
                                    <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">{{ $step['label'] }}</p>
                                </div>
                                <h3 class="mt-4 text-base font-bold text-blue-950 dark:text-white">{{ $step['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $step['body'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="bg-slate-100 dark:bg-slate-900">
                <div class="mx-auto grid max-w-7xl gap-6 px-4 py-10 sm:px-6 sm:py-14 lg:grid-cols-[0.9fr_1.1fr] lg:px-8">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">User Access</p>
                        <h2 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl dark:text-white">Organized by role</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
                            Each user sees the tools needed for their patrol responsibility. Supervisors manage and monitor; guards scan and submit.
                        </p>
                    </div>

                    <div class="grid gap-3 sm:grid-cols-2">
                        <article class="rounded-md border border-blue-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                            <div class="flex items-center gap-3">
                                <img src="{{ asset('images/user-icons/supervisor-account.png') }}" alt="" class="h-12 w-12 rounded-md border border-blue-100 bg-blue-50 object-contain p-1 dark:border-slate-700 dark:bg-slate-900">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">Supervisor</p>
                                    <h3 class="text-lg font-bold text-blue-950 dark:text-white">Manage and review</h3>
                                </div>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">Create guard accounts, maintain checkpoints, monitor logs, review incidents, audit activity, and export reports.</p>
                        </article>

                        <article class="rounded-md border border-blue-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                            <div class="flex items-center gap-3">
                                <img src="{{ asset('images/user-icons/guard-account.png') }}" alt="" class="h-12 w-12 rounded-md border border-blue-100 bg-blue-50 object-contain p-1 dark:border-slate-700 dark:bg-slate-900">
                                <div>
                                    <p class="text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Guard</p>
                                    <h3 class="text-lg font-bold text-blue-950 dark:text-white">Scan and submit</h3>
                                </div>
                            </div>
                            <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">Scan RFID checkpoints, capture area selfies, complete checklist items, attach proof photos, and report incidents.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="border-y border-blue-100 bg-white dark:border-slate-800 dark:bg-slate-950">
                <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
                    <div class="max-w-3xl">
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">Records</p>
                        <h2 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl dark:text-white">What the system keeps organized</h2>
                    </div>

                    <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($recordItems as $item)
                            <article class="rounded-md border border-blue-100 bg-slate-50 p-4 dark:border-slate-800 dark:bg-slate-900">
                                <h3 class="text-base font-bold text-blue-950 dark:text-white">{{ $item['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $item['body'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="bg-slate-100 dark:bg-slate-900">
                <div class="mx-auto flex max-w-7xl flex-col gap-5 px-4 py-10 sm:px-6 sm:py-14 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                    <div class="max-w-2xl">
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">Ready for daily use</p>
                        <h2 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl dark:text-white">Open the system or install it on a phone</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600 dark:text-slate-300">Use the browser login for supervisors and guards, or install the app when the device supports PWA installation.</p>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <a href="{{ $systemHref }}" class="inline-flex h-11 items-center justify-center rounded-md bg-blue-700 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900">
                            {{ auth()->check() ? 'Open System' : 'Log in' }}
                        </a>
                        <button
                            type="button"
                            class="inline-flex h-11 items-center justify-center rounded-md border border-blue-100 bg-white px-5 text-sm font-bold text-blue-700 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-80 dark:border-slate-700 dark:bg-slate-950 dark:text-blue-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900"
                            @click="install"
                            :disabled="isBusy()"
                        >
                            <span x-text="installLabel()">Install App</span>
                        </button>
                    </div>
                </div>
            </section>
        </main>

        <footer class="border-t border-blue-100 bg-white dark:border-slate-800 dark:bg-slate-950">
            <div class="mx-auto flex max-w-7xl flex-col gap-4 px-4 py-7 text-sm text-slate-500 sm:px-6 md:flex-row md:items-center md:justify-between lg:px-8 dark:text-slate-400">
                <div>
                    <p class="font-bold text-blue-950 dark:text-white">SLSU Bontoc Patrol</p>
                    <p class="mt-1">Security and Safety Services Office</p>
                </div>
                <p>{{ now()->year }} Southern Leyte State University - Bontoc Campus</p>
            </div>
        </footer>
    </body>
</html>
