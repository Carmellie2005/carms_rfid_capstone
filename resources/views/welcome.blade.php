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
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    @php
        $systemHref = auth()->check()
            ? (auth()->user()->role === 'guard' ? route('patrol.scan') : route('dashboard'))
            : route('login');
        $pwaStartHref = Route::has('login') ? route('login') : url('/');
    @endphp
    <body x-data="pwaInstallPrompt({ appName: 'BC Patrol', startUrl: @js($pwaStartHref) })" class="bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
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
                        <span>Open BC Patrol</span>
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
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-3 sm:px-6 sm:py-4 lg:px-8" aria-label="Main navigation">
                <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3">
                    <x-application-logo class="h-10 w-10 shrink-0 sm:h-11 sm:w-11" />
                    <span class="leading-tight">
                        <span class="block text-sm font-semibold text-blue-950 sm:text-base dark:text-blue-100">SLSU Bontoc Patrol</span>
                    </span>
                </a>

                <div class="flex items-center gap-2 sm:gap-3">
                    <x-theme-toggle />

                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:px-4 dark:focus:ring-offset-slate-950">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-md bg-blue-700 px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:px-4 dark:focus:ring-offset-slate-950">
                                Log in
                            </a>
                        @endauth
                    @endif
                </div>
            </nav>
        </header>

        <main>
            <section class="relative overflow-hidden border-b border-blue-100 bg-slate-100 dark:border-slate-800 dark:bg-slate-900">
                <img
                    src="{{ asset('images/homepage-hero-background.png') }}"
                    alt=""
                    class="pointer-events-none absolute inset-0 h-full w-full object-cover"
                    aria-hidden="true"
                >
                <div class="pointer-events-none absolute inset-0 bg-slate-950/58 dark:bg-slate-950/70"></div>
                <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_center,rgba(15,23,42,0.34),rgba(15,23,42,0.78))]"></div>

                <div class="relative mx-auto flex min-h-[calc(100svh-128px)] max-w-7xl items-center justify-center px-4 py-10 text-center sm:min-h-[calc(100svh-80px)] sm:px-6 sm:py-14 lg:px-8">
                    <div class="max-w-4xl">
                        <p class="mx-auto mb-3 inline-flex max-w-full items-center justify-center rounded-md border border-white/40 bg-white/15 px-3 py-1.5 text-[0.68rem] font-bold uppercase tracking-wide text-blue-50 shadow-lg shadow-slate-950/20 backdrop-blur sm:mb-4 sm:px-4 sm:py-2 sm:text-xs">
                            Southern Leyte State University - Bontoc Campus
                        </p>
                        <h1 class="text-3xl font-bold leading-tight text-white drop-shadow-lg sm:text-5xl">
                            Secure Campus Patrol, Smarter Incident Reporting
                        </h1>
                        <p class="mx-auto mt-4 max-w-3xl text-sm font-medium leading-6 text-blue-50 drop-shadow sm:mt-5 sm:text-lg sm:leading-7">
                            A focused patrol system for SLSU Bontoc Campus, built to record checkpoint visits, verify guards, complete patrol checklists, and submit incident reports.
                        </p>
                        <div class="mt-6 sm:mt-8">
                            <div class="flex flex-col justify-center gap-2.5 sm:flex-row sm:gap-3">
                                <button
                                    type="button"
                                    class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-white px-4 py-2.5 text-sm font-bold text-blue-950 shadow-lg shadow-slate-950/20 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 focus:ring-offset-slate-950 disabled:cursor-wait disabled:opacity-80 sm:min-h-12 sm:px-5 sm:py-3"
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
                                    <svg x-show="! isBusy() && installLabel() === 'Open App'" x-cloak class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M5 12h12m0 0-4-4m4 4-4 4M5 5h14v14H5V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span x-text="installLabel()">Install Now</span>
                                </button>

                                <a href="{{ $systemHref }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-slate-950/20 transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 focus:ring-offset-slate-950 sm:min-h-12 sm:px-5 sm:py-3">
                                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M5 12h12m0 0-4-4m4 4-4 4M5 5h14v14H5V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span>Open System</span>
                                </a>

                                <a href="#system-flow" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-md border border-white/80 bg-slate-950/20 px-4 py-2.5 text-sm font-semibold text-white shadow-lg shadow-slate-950/20 backdrop-blur transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 focus:ring-offset-slate-950 sm:min-h-12 sm:px-5 sm:py-3">
                                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M5 5h6v6H5V5Zm8 0h6v6h-6V5ZM5 13h6v6H5v-6Zm8 0h6v6h-6v-6Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                    </svg>
                                    <span>View Roles</span>
                                </a>
                            </div>

                            <p
                                x-cloak
                                x-show="message"
                                x-text="message"
                                class="mx-auto mt-3 max-w-xl rounded-md bg-slate-950/45 px-4 py-2 text-sm font-medium text-blue-50 shadow-sm"
                            ></p>
                        </div>
                    </div>
                </div>
            </section>

            <section id="system-flow" class="border-b border-blue-100 bg-white dark:border-slate-800 dark:bg-slate-950">
                <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-14 lg:px-8">
                    <div class="max-w-3xl">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 sm:text-sm dark:text-emerald-300">System Flow</p>
                        <h2 class="mt-2 text-2xl font-bold tracking-tight text-blue-950 sm:mt-3 sm:text-3xl dark:text-white">From guard setup to verified patrol records</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600 sm:mt-4 sm:text-base sm:leading-7 dark:text-slate-300">
                            This system is for the patrol and incident monitoring needs of Southern Leyte State University - Bontoc Campus, handled by the Security and Safety Services Office. It connects guard profiles, RFID checkpoint scans, live face verification, checklist responses, and incident reports in one place.
                        </p>
                        <div class="mt-4 rounded-md border border-blue-100 bg-blue-50/70 p-3 text-xs font-semibold leading-5 text-blue-950 sm:mt-5 sm:p-4 sm:text-sm sm:leading-6 dark:border-slate-800 dark:bg-slate-900 dark:text-blue-100">
                            Built for SLSU Bontoc Campus operations and managed by the Security and Safety Services Office.
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-2 sm:mt-8 sm:gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <article class="rounded-md border border-emerald-100 bg-emerald-50/60 p-3 dark:border-emerald-900 dark:bg-emerald-950/30 sm:p-5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-emerald-600 text-white sm:h-10 sm:w-10">
                                <svg class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <h3 class="mt-3 text-sm font-bold text-slate-950 sm:mt-4 sm:text-lg dark:text-white">Complete Profile</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-600 sm:mt-2 sm:text-sm sm:leading-6 dark:text-slate-300">Supervisor and guard accounts keep identity, contact, shift, RFID, and status information ready.</p>
                        </article>

                        <article class="rounded-md border border-sky-100 bg-sky-50/70 p-3 dark:border-sky-900 dark:bg-sky-950/30 sm:p-5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-sky-600 text-white sm:h-10 sm:w-10">
                                <svg class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5 5h5v5H5V5Zm9 0h5v5h-5V5ZM5 14h5v5H5v-5Zm10 1h4m-4 4h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <h3 class="mt-3 text-sm font-bold text-slate-950 sm:mt-4 sm:text-lg dark:text-white">Scan Checkpoint</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-600 sm:mt-2 sm:text-sm sm:leading-6 dark:text-slate-300">Guards scan assigned RFID checkpoints to create time-stamped patrol logs.</p>
                        </article>

                        <article class="rounded-md border border-violet-100 bg-violet-50/70 p-3 dark:border-violet-900 dark:bg-violet-950/30 sm:p-5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-violet-600 text-white sm:h-10 sm:w-10">
                                <svg class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4 12s3-6 8-6 8 6 8 6-3 6-8 6-8-6-8-6Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" />
                                </svg>
                            </div>
                            <h3 class="mt-3 text-sm font-bold text-slate-950 sm:mt-4 sm:text-lg dark:text-white">Verify Guard</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-600 sm:mt-2 sm:text-sm sm:leading-6 dark:text-slate-300">Live face registration helps confirm the guard completing the patrol scan.</p>
                        </article>

                        <article class="rounded-md border border-amber-100 bg-amber-50/70 p-3 dark:border-amber-900 dark:bg-amber-950/30 sm:p-5">
                            <div class="flex h-8 w-8 items-center justify-center rounded-md bg-amber-600 text-white sm:h-10 sm:w-10">
                                <svg class="h-4 w-4 sm:h-5 sm:w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                    <path d="M14 3v5h5M8.5 14h7M8.5 17h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <h3 class="mt-3 text-sm font-bold text-slate-950 sm:mt-4 sm:text-lg dark:text-white">Review Reports</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-600 sm:mt-2 sm:text-sm sm:leading-6 dark:text-slate-300">Supervisors review logs, incidents, statuses, and downloadable PDF records.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-14 lg:px-8">
                <div class="grid gap-3 sm:gap-6 lg:grid-cols-2">
                    <article class="rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 sm:text-sm dark:text-blue-300">For Supervisors</p>
                        <h2 class="mt-2 text-lg font-bold text-blue-950 sm:mt-3 sm:text-xl dark:text-white">Manage people, checkpoints, and reports</h2>
                        <ul class="mt-4 space-y-2 text-xs leading-5 text-slate-600 sm:mt-5 sm:space-y-3 sm:text-sm sm:leading-6 dark:text-slate-300">
                            <li class="flex gap-3">
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                                Create guard accounts with employee number, contact details, shift assignment, RFID UID, active status, and live face reference.
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-sky-500"></span>
                                Maintain checkpoint records and connect each checkpoint to the correct RFID card or scanner reference.
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-amber-500"></span>
                                Monitor patrol logs, incident submissions, report status, dashboard summaries, and downloadable PDF documentation.
                            </li>
                        </ul>
                    </article>

                    <article class="rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 sm:text-sm dark:text-blue-300">For Security Guards</p>
                        <h2 class="mt-2 text-lg font-bold text-blue-950 sm:mt-3 sm:text-xl dark:text-white">Complete patrol work from a mobile phone</h2>
                        <ul class="mt-4 space-y-2 text-xs leading-5 text-slate-600 sm:mt-5 sm:space-y-3 sm:text-sm sm:leading-6 dark:text-slate-300">
                            <li class="flex gap-3">
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-emerald-500"></span>
                                Keep profile information complete, including contact details, assigned shift, profile photo, and one-time live face registration.
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-sky-500"></span>
                                Scan checkpoints, pass live face verification, answer checklist items, and submit valid patrol entries.
                            </li>
                            <li class="flex gap-3">
                                <span class="mt-1 h-2 w-2 shrink-0 rounded-full bg-rose-500"></span>
                                File incident reports with details, priority, location, evidence, and PDF access for completed records.
                            </li>
                        </ul>
                    </article>
                </div>
            </section>

            <section class="border-y border-blue-100 bg-slate-100 py-8 sm:py-14 dark:border-slate-800 dark:bg-slate-900">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid gap-5 sm:gap-8 lg:grid-cols-[0.9fr_1.5fr] lg:items-start">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700 sm:text-sm dark:text-blue-300">System Records</p>
                            <h2 class="mt-2 text-2xl font-bold tracking-tight text-blue-950 sm:mt-3 sm:text-3xl dark:text-white">Important details stay traceable</h2>
                            <p class="mt-3 text-sm leading-6 text-slate-600 sm:mt-4 sm:text-base sm:leading-7 dark:text-slate-300">
                                Each record supports daily patrol monitoring and review, from the guard profile setup to the final report PDF.
                            </p>
                        </div>

                        <div class="grid grid-cols-2 gap-2 sm:gap-4">
                            <article class="rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-950">
                                <h3 class="text-sm font-bold text-blue-950 sm:text-base dark:text-white">Profile Completion</h3>
                                <p class="mt-1 text-xs leading-5 text-slate-600 sm:mt-2 sm:text-sm sm:leading-6 dark:text-slate-300">Shows account details, photo status, active status, and face registration readiness.</p>
                            </article>

                            <article class="rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-950">
                                <h3 class="text-sm font-bold text-blue-950 sm:text-base dark:text-white">RFID Checkpoints</h3>
                                <p class="mt-1 text-xs leading-5 text-slate-600 sm:mt-2 sm:text-sm sm:leading-6 dark:text-slate-300">Keeps checkpoint names, locations, reader IDs, and active status organized.</p>
                            </article>

                            <article class="rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-950">
                                <h3 class="text-sm font-bold text-blue-950 sm:text-base dark:text-white">Patrol Logs</h3>
                                <p class="mt-1 text-xs leading-5 text-slate-600 sm:mt-2 sm:text-sm sm:leading-6 dark:text-slate-300">Stores guard identity, checkpoint, scan time, verification status, and checklist answers.</p>
                            </article>

                            <article class="rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-5 dark:border-slate-800 dark:bg-slate-950">
                                <h3 class="text-sm font-bold text-blue-950 sm:text-base dark:text-white">Incident Reports</h3>
                                <p class="mt-1 text-xs leading-5 text-slate-600 sm:mt-2 sm:text-sm sm:leading-6 dark:text-slate-300">Includes priority, location, description, evidence, status, and PDF output.</p>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 sm:py-14 lg:px-8">
                <div class="grid gap-4 sm:gap-6 lg:grid-cols-[1.3fr_0.7fr] lg:items-center">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700 sm:text-sm dark:text-emerald-300">Mobile Ready</p>
                        <h2 class="mt-2 text-2xl font-bold tracking-tight text-blue-950 sm:mt-3 sm:text-3xl dark:text-white">Installable for faster phone access</h2>
                        <p class="mt-3 text-sm leading-6 text-slate-600 sm:mt-4 sm:text-base sm:leading-7 dark:text-slate-300">
                            The homepage includes a PWA install button so guards and supervisors can open the system from the phone home screen when the browser supports installation.
                        </p>
                    </div>

                    <div class="rounded-md border border-blue-100 bg-white p-4 shadow-sm sm:p-6 dark:border-slate-800 dark:bg-slate-900">
                        <x-application-logo class="mx-auto h-16 w-16 sm:h-20 sm:w-20" />
                        <button
                            type="button"
                            class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-md bg-blue-700 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-wait disabled:opacity-80 sm:mt-5 sm:px-5 sm:py-3 dark:focus:ring-offset-slate-900"
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
                            <svg x-show="! isBusy() && installLabel() === 'Open App'" x-cloak class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M5 12h12m0 0-4-4m4 4-4 4M5 5h14v14H5V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span x-text="installLabel()">Install Now</span>
                        </button>

                        <p x-cloak x-show="message" x-text="message" class="mt-3 text-center text-xs font-medium text-slate-500 sm:text-sm dark:text-slate-400"></p>
                    </div>
                </div>
            </section>

        </main>

        <footer class="border-t border-blue-100 bg-white dark:border-slate-800 dark:bg-slate-900">
            <div class="mx-auto grid max-w-7xl gap-8 px-4 py-9 sm:px-6 md:grid-cols-[1fr_1.6fr] md:items-start lg:px-8">
                <div>
                    <p class="text-2xl font-bold tracking-tight text-blue-950 dark:text-white">SLSU Bontoc Patrol</p>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">&copy; {{ now()->year }} All rights reserved.</p>
                </div>

                <div class="grid gap-x-8 gap-y-3 border-blue-100 md:grid-cols-2 md:border-l md:pl-8 dark:border-slate-700">
                    <p class="text-base font-semibold text-blue-950 dark:text-white">Carmela B. Hernandez <span class="text-sm font-medium text-slate-500 dark:text-slate-400">(Lead Programmer)</span></p>
                    <p class="text-base font-semibold text-blue-950 dark:text-white">Cherry Ann R. Himo <span class="text-sm font-medium text-slate-500 dark:text-slate-400">(System Analyst)</span></p>
                    <p class="text-base font-semibold text-blue-950 dark:text-white">Clarice R. Gumapi <span class="text-sm font-medium text-slate-500 dark:text-slate-400">(Documentation Specialist)</span></p>
                    <p class="text-base font-semibold text-blue-950 dark:text-white">Karyl G. Viure <span class="text-sm font-medium text-slate-500 dark:text-slate-400">(Quality Assurance)</span></p>
                </div>
            </div>
        </footer>

    </body>
</html>
