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
            ['label' => 'Home', 'href' => '#home'],
            ['label' => 'System Flow', 'href' => '#system-flow'],
            ['label' => 'Users', 'href' => '#users'],
            ['label' => 'Records', 'href' => '#records'],
            ['label' => 'Install App', 'href' => '#install-app'],
        ];

        $flowSteps = [
            ['number' => '01', 'title' => 'Register guard account', 'body' => 'Supervisor creates the guard profile, assigns RFID UID, shift, and account status.'],
            ['number' => '02', 'title' => 'Scan checkpoint', 'body' => 'Guard taps the RFID card at a checkpoint reader to start a patrol record.'],
            ['number' => '03', 'title' => 'Submit patrol proof', 'body' => 'Guard adds area selfie, GPS/location details, checklist answers, and proof photos.'],
            ['number' => '04', 'title' => 'Review and report', 'body' => 'Supervisor checks patrol logs, incident reports, scan issues, audit trail, and PDF records.'],
        ];

        $recordItems = [
            ['title' => 'RFID Checkpoints', 'body' => 'BITS, Guard House, Campus Canteen, MPC, and Tilapia Hatchery checkpoint records.'],
            ['title' => 'Patrol Logs', 'body' => 'Guard identity, checkpoint, scan time, area selfie, location details, checklist status, and proof photos.'],
            ['title' => 'Incident Reports', 'body' => 'Incident category, priority, description, evidence photos, action taken, status, and resolved date.'],
            ['title' => 'Account Control', 'body' => 'Supervisor-managed guard accounts, password reset, active status, and audit trail.'],
        ];
    @endphp
    <body x-data="pwaInstallPrompt({ appName: 'SLSU Bontoc Patrol', startUrl: @js($pwaStartHref) })" class="bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
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

        <header class="sticky top-0 z-30 border-b border-blue-100 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
            <nav class="mx-auto flex max-w-7xl items-center justify-between gap-3 px-4 py-3 sm:px-6 lg:px-8" aria-label="Main navigation">
                <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3">
                    <x-application-logo class="h-10 w-10 shrink-0 sm:h-11 sm:w-11" />
                    <span class="min-w-0 leading-tight">
                        <span class="block truncate text-sm font-bold text-blue-950 sm:text-base dark:text-white">SLSU Bontoc Patrol</span>
                        <span class="block truncate text-xs font-medium text-blue-600 dark:text-blue-300">Security Monitoring</span>
                    </span>
                </a>

                <div class="hidden items-center gap-1 lg:flex">
                    @foreach ($menuItems as $item)
                        <a href="{{ $item['href'] }}" class="rounded-md px-3 py-2 text-sm font-semibold text-slate-600 transition hover:bg-blue-50 hover:text-blue-700 dark:text-slate-300 dark:hover:bg-slate-900 dark:hover:text-blue-200">
                            {{ $item['label'] }}
                        </a>
                    @endforeach
                </div>

                <div class="flex items-center gap-2">
                    <x-theme-toggle />

                    @if (Route::has('login'))
                        <a href="{{ $systemHref }}" class="inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:px-4 dark:focus:ring-offset-slate-950">
                            {{ auth()->check() ? 'Open System' : 'Log in' }}
                        </a>
                    @endif
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
            <section class="border-b border-blue-100 bg-white dark:border-slate-800 dark:bg-slate-950">
                <div class="mx-auto max-w-7xl px-4 pb-8 pt-5 sm:px-6 sm:pb-12 sm:pt-8 lg:px-8">
                    <div class="overflow-hidden rounded-md border border-blue-100 bg-slate-100 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <img
                            src="{{ asset('images/homepage-hero-background.png') }}"
                            alt="Southern Leyte State University Bontoc Campus administration building"
                            class="h-[18rem] w-full object-cover object-center sm:h-[26rem] lg:h-[31rem]"
                        >
                    </div>

                    <div class="mt-6 grid gap-6 lg:grid-cols-[1.25fr_0.75fr] lg:items-end">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">Southern Leyte State University - Bontoc Campus</p>
                            <h1 class="mt-2 max-w-4xl text-3xl font-bold leading-tight text-blue-950 sm:text-5xl dark:text-white">
                                Secure Campus Patrol and Incident Reporting
                            </h1>
                            <p class="mt-4 max-w-3xl text-sm leading-6 text-slate-600 sm:text-base sm:leading-7 dark:text-slate-300">
                                A focused patrol system for recording RFID checkpoint visits, area selfie proof, patrol checklist responses, and incident reports for campus security monitoring.
                            </p>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row lg:justify-end">
                            <a href="{{ $systemHref }}" class="inline-flex h-11 items-center justify-center gap-2 rounded-md bg-blue-700 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                                <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5 12h12m0 0-4-4m4 4-4 4M5 5h14v14H5V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                                <span>{{ auth()->check() ? 'Open System' : 'Log in to System' }}</span>
                            </a>

                            <button
                                type="button"
                                class="inline-flex h-11 items-center justify-center gap-2 rounded-md border border-blue-100 bg-white px-5 text-sm font-bold text-blue-700 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-80 dark:border-slate-700 dark:bg-slate-900 dark:text-blue-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950"
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
                    </div>

                    <p
                        x-cloak
                        x-show="message"
                        x-text="message"
                        class="mt-3 rounded-md border border-blue-100 bg-blue-50 px-4 py-2 text-sm font-medium text-blue-800 dark:border-blue-400/30 dark:bg-blue-950/40 dark:text-blue-100"
                    ></p>
                </div>
            </section>

            <section id="system-flow" class="border-b border-blue-100 bg-slate-50 dark:border-slate-800 dark:bg-slate-900">
                <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
                    <div class="max-w-3xl">
                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">System Flow</p>
                        <h2 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl dark:text-white">From guard setup to verified patrol records</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600 sm:text-base sm:leading-7 dark:text-slate-300">
                            The homepage flow follows the actual system process: account registration, RFID checkpoint scan, patrol documentation, and supervisor review.
                        </p>
                    </div>

                    <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($flowSteps as $step)
                            <article class="rounded-md border border-blue-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                                <p class="text-sm font-bold text-blue-700 dark:text-blue-300">{{ $step['number'] }}</p>
                                <h3 class="mt-3 text-base font-bold text-blue-950 dark:text-white">{{ $step['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $step['body'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="users" class="border-b border-blue-100 bg-white dark:border-slate-800 dark:bg-slate-950">
                <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
                    <div class="grid gap-6 lg:grid-cols-[0.85fr_1.15fr] lg:items-start">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">User Access</p>
                            <h2 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl dark:text-white">Organized by responsibility</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600 sm:text-base sm:leading-7 dark:text-slate-300">
                                Supervisors handle monitoring and management. Guards handle checkpoint scanning and patrol documentation.
                            </p>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2">
                            <article class="rounded-md border border-blue-100 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-900">
                                <div class="flex items-center gap-3">
                                    <img src="{{ asset('images/user-icons/supervisor-account.png') }}" alt="" class="h-12 w-12 rounded-md border border-blue-100 bg-white object-contain p-1 dark:border-slate-700 dark:bg-slate-950">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">Supervisor</p>
                                        <h3 class="text-lg font-bold text-blue-950 dark:text-white">Manage and monitor</h3>
                                    </div>
                                </div>
                                <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">Create guard accounts, maintain checkpoints, review logs and incidents, check scan issues, and export reports.</p>
                            </article>

                            <article class="rounded-md border border-blue-100 bg-slate-50 p-5 dark:border-slate-800 dark:bg-slate-900">
                                <div class="flex items-center gap-3">
                                    <img src="{{ asset('images/user-icons/guard-account.png') }}" alt="" class="h-12 w-12 rounded-md border border-blue-100 bg-white object-contain p-1 dark:border-slate-700 dark:bg-slate-950">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Guard</p>
                                        <h3 class="text-lg font-bold text-blue-950 dark:text-white">Scan and submit</h3>
                                    </div>
                                </div>
                                <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">Scan RFID checkpoints, capture area selfies, complete checklist items, upload proof photos, and submit incident reports.</p>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section id="records" class="border-b border-blue-100 bg-slate-50 dark:border-slate-800 dark:bg-slate-900">
                <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 sm:py-14 lg:px-8">
                    <div class="max-w-3xl">
                        <p class="text-xs font-bold uppercase tracking-wide text-blue-700 dark:text-blue-300">System Records</p>
                        <h2 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl dark:text-white">Important data stays traceable</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600 sm:text-base sm:leading-7 dark:text-slate-300">
                            Records are grouped around patrol monitoring, incident documentation, and account control.
                        </p>
                    </div>

                    <div class="mt-7 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        @foreach ($recordItems as $item)
                            <article class="rounded-md border border-blue-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                                <h3 class="text-base font-bold text-blue-950 dark:text-white">{{ $item['title'] }}</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ $item['body'] }}</p>
                            </article>
                        @endforeach
                    </div>
                </div>
            </section>

            <section id="install-app" class="bg-white dark:bg-slate-950">
                <div class="mx-auto flex max-w-7xl flex-col gap-5 px-4 py-10 sm:px-6 sm:py-14 lg:flex-row lg:items-center lg:justify-between lg:px-8">
                    <div class="max-w-2xl">
                        <p class="text-xs font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Mobile Ready</p>
                        <h2 class="mt-2 text-2xl font-bold text-blue-950 sm:text-3xl dark:text-white">Open the system or install it on a phone</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600 sm:text-base sm:leading-7 dark:text-slate-300">
                            Guards and supervisors can use the system through the browser. Supported devices can also install it for quicker access.
                        </p>
                    </div>

                    <div class="flex flex-col gap-2 sm:flex-row">
                        <a href="{{ $systemHref }}" class="inline-flex h-11 items-center justify-center rounded-md bg-blue-700 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                            {{ auth()->check() ? 'Open System' : 'Log in' }}
                        </a>
                        <button
                            type="button"
                            class="inline-flex h-11 items-center justify-center rounded-md border border-blue-100 bg-white px-5 text-sm font-bold text-blue-700 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-80 dark:border-slate-700 dark:bg-slate-900 dark:text-blue-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950"
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
