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
    <body x-data="pwaInstallPrompt()" class="bg-slate-50 font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        @php
            $systemHref = auth()->check()
                ? (auth()->user()->role === 'guard' ? route('patrol.scan') : route('dashboard'))
                : route('login');
        @endphp

        <header class="sticky top-0 z-30 border-b border-blue-100 bg-white/95 backdrop-blur dark:border-slate-800 dark:bg-slate-950/95">
            <nav class="mx-auto flex max-w-7xl items-center justify-between px-4 py-4 sm:px-6 lg:px-8" aria-label="Main navigation">
                <a href="{{ url('/') }}" class="flex min-w-0 items-center gap-3">
                    <x-application-logo class="h-11 w-11 shrink-0" />
                    <span class="leading-tight">
                        <span class="block text-sm font-semibold text-blue-950 sm:text-base dark:text-blue-100">SLSU Bontoc Patrol</span>
                    </span>
                </a>

                <div class="flex items-center gap-2 sm:gap-3">
                    <x-theme-toggle />

                    @if (Route::has('login'))
                        @auth
                            <a href="{{ url('/dashboard') }}" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                                Dashboard
                            </a>
                        @else
                            <a href="{{ route('login') }}" class="rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
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

                <div class="relative mx-auto flex min-h-[calc(100svh-80px)] max-w-7xl items-center justify-center px-4 py-14 text-center sm:px-6 lg:px-8">
                    <div class="max-w-4xl">
                        <p class="mx-auto mb-4 inline-flex items-center justify-center rounded-full border border-white/40 bg-white/15 px-4 py-2 text-xs font-bold uppercase tracking-wide text-blue-50 shadow-lg shadow-slate-950/20 backdrop-blur">
                            Southern Leyte State University - Bontoc Campus
                        </p>
                        <h1 class="text-4xl font-bold leading-tight text-white drop-shadow-lg sm:text-5xl">
                            Secure Campus Patrol, Smarter Incident Reporting
                        </h1>
                        <p class="mx-auto mt-5 max-w-3xl text-base font-medium leading-7 text-blue-50 drop-shadow sm:text-lg">
                            A focused patrol system for SLSU Bontoc Campus, built to record checkpoint visits, verify guards, complete patrol checklists, and submit incident reports.
                        </p>
                        <div class="mt-8">
                            <div class="flex flex-col justify-center gap-3 sm:flex-row">
                                <button
                                    type="button"
                                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-white px-5 py-3 text-sm font-bold text-blue-950 shadow-lg shadow-slate-950/20 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 focus:ring-offset-slate-950"
                                    @click="install"
                                >
                                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M12 3v10m0 0 4-4m-4 4-4-4M5 15v3a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3v-3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span x-text="installLabel()">Install Now</span>
                                </button>

                                <a href="{{ $systemHref }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-950/20 transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 focus:ring-offset-slate-950">
                                    <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                        <path d="M5 12h12m0 0-4-4m4 4-4 4M5 5h14v14H5V5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                    </svg>
                                    <span>Open System</span>
                                </a>

                                <a href="#system-flow" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-md border border-white/80 bg-slate-950/20 px-5 py-3 text-sm font-semibold text-white shadow-lg shadow-slate-950/20 backdrop-blur transition hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-blue-200 focus:ring-offset-2 focus:ring-offset-slate-950">
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
                <div class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                    <div class="max-w-3xl">
                        <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">System Flow</p>
                        <h2 class="mt-3 text-3xl font-bold tracking-tight text-blue-950 dark:text-white">From guard setup to verified patrol records</h2>
                        <p class="mt-4 text-base leading-7 text-slate-600 dark:text-slate-300">
                            This system is for the patrol and incident monitoring needs of Southern Leyte State University - Bontoc Campus, handled by the Security and Safety Services Office. It connects guard profiles, RFID checkpoint scans, live face verification, checklist responses, and incident reports in one place.
                        </p>
                        <div class="mt-5 rounded-lg border border-blue-100 bg-blue-50/70 p-4 text-sm font-semibold leading-6 text-blue-950 dark:border-slate-800 dark:bg-slate-900 dark:text-blue-100">
                            Built for SLSU Bontoc Campus operations and managed by the Security and Safety Services Office.
                        </div>
                    </div>

                    <div class="mt-8 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <article class="rounded-lg border border-emerald-100 bg-emerald-50/60 p-5 dark:border-emerald-900 dark:bg-emerald-950/30">
                            <div class="flex h-10 w-10 items-center justify-center rounded-md bg-emerald-600 text-white">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M20 21a8 8 0 0 0-16 0M12 13a5 5 0 1 0 0-10 5 5 0 0 0 0 10Z" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-lg font-bold text-slate-950 dark:text-white">Complete Profile</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Supervisor and guard accounts keep required identity, contact, shift, RFID, and status information ready before patrol work begins.</p>
                        </article>

                        <article class="rounded-lg border border-sky-100 bg-sky-50/70 p-5 dark:border-sky-900 dark:bg-sky-950/30">
                            <div class="flex h-10 w-10 items-center justify-center rounded-md bg-sky-600 text-white">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M5 5h5v5H5V5Zm9 0h5v5h-5V5ZM5 14h5v5H5v-5Zm10 1h4m-4 4h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-lg font-bold text-slate-950 dark:text-white">Scan Checkpoint</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Guards scan assigned RFID checkpoints to create time-stamped patrol logs tied to the checkpoint location and guard record.</p>
                        </article>

                        <article class="rounded-lg border border-violet-100 bg-violet-50/70 p-5 dark:border-violet-900 dark:bg-violet-950/30">
                            <div class="flex h-10 w-10 items-center justify-center rounded-md bg-violet-600 text-white">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M4 12s3-6 8-6 8 6 8 6-3 6-8 6-8-6-8-6Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                    <path d="M12 15a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" stroke="currentColor" stroke-width="2" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-lg font-bold text-slate-950 dark:text-white">Verify Guard</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Live face registration and verification help confirm that the assigned guard is the one completing the patrol scan.</p>
                        </article>

                        <article class="rounded-lg border border-amber-100 bg-amber-50/70 p-5 dark:border-amber-900 dark:bg-amber-950/30">
                            <div class="flex h-10 w-10 items-center justify-center rounded-md bg-amber-600 text-white">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M7 3h7l5 5v13H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="2" stroke-linejoin="round" />
                                    <path d="M14 3v5h5M8.5 14h7M8.5 17h4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </div>
                            <h3 class="mt-4 text-lg font-bold text-slate-950 dark:text-white">Review Reports</h3>
                            <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Supervisors can review logs, monitor incidents, update report status, and download PDF records for documentation.</p>
                        </article>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-2">
                    <article class="rounded-lg border border-blue-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">For Supervisors</p>
                        <h2 class="mt-3 text-xl font-bold text-blue-950 dark:text-white">Manage people, checkpoints, and reports</h2>
                        <ul class="mt-5 space-y-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
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

                    <article class="rounded-lg border border-blue-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <p class="text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">For Security Guards</p>
                        <h2 class="mt-3 text-xl font-bold text-blue-950 dark:text-white">Complete patrol work from a mobile phone</h2>
                        <ul class="mt-5 space-y-3 text-sm leading-6 text-slate-600 dark:text-slate-300">
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

            <section class="border-y border-blue-100 bg-slate-100 py-14 dark:border-slate-800 dark:bg-slate-900">
                <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div class="grid gap-8 lg:grid-cols-[0.9fr_1.5fr] lg:items-start">
                        <div>
                            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700 dark:text-blue-300">System Records</p>
                            <h2 class="mt-3 text-3xl font-bold tracking-tight text-blue-950 dark:text-white">Important details stay traceable</h2>
                            <p class="mt-4 text-base leading-7 text-slate-600 dark:text-slate-300">
                                Each record supports daily patrol monitoring and review, from the guard profile setup to the final report PDF.
                            </p>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <article class="rounded-lg border border-blue-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                                <h3 class="text-base font-bold text-blue-950 dark:text-white">Profile Completion</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Completion percentage shows missing account details, profile photo status, active status, and face registration readiness.</p>
                            </article>

                            <article class="rounded-lg border border-blue-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                                <h3 class="text-base font-bold text-blue-950 dark:text-white">RFID Checkpoints</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Checkpoint names, locations, RFID UIDs, and active status keep patrol scanning organized and easier to audit.</p>
                            </article>

                            <article class="rounded-lg border border-blue-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                                <h3 class="text-base font-bold text-blue-950 dark:text-white">Patrol Logs</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Every checkpoint scan stores guard identity, checkpoint, scan time, verification status, and checklist answers.</p>
                            </article>

                            <article class="rounded-lg border border-blue-100 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-950">
                                <h3 class="text-base font-bold text-blue-950 dark:text-white">Incident Reports</h3>
                                <p class="mt-2 text-sm leading-6 text-slate-600 dark:text-slate-300">Reports include priority, location, description, evidence, supervisor status, and downloadable PDF output.</p>
                            </article>
                        </div>
                    </div>
                </div>
            </section>

            <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6 lg:px-8">
                <div class="grid gap-6 lg:grid-cols-[1.3fr_0.7fr] lg:items-center">
                    <div>
                        <p class="text-sm font-semibold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Mobile Ready</p>
                        <h2 class="mt-3 text-3xl font-bold tracking-tight text-blue-950 dark:text-white">Installable for faster phone access</h2>
                        <p class="mt-4 text-base leading-7 text-slate-600 dark:text-slate-300">
                            The homepage includes a PWA install button so guards and supervisors can open the system from the phone home screen when the browser supports installation.
                        </p>
                    </div>

                    <div class="rounded-lg border border-blue-100 bg-white p-6 shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <x-application-logo class="mx-auto h-20 w-20" />
                        <button
                            type="button"
                            class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-md bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-900"
                            @click="install"
                        >
                            <svg class="h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 3v10m0 0 4-4m-4 4-4-4M5 15v3a3 3 0 0 0 3 3h8a3 3 0 0 0 3-3v-3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <span x-text="installLabel()">Install Now</span>
                        </button>

                        <p x-cloak x-show="message" x-text="message" class="mt-3 text-center text-sm font-medium text-slate-500 dark:text-slate-400"></p>
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
