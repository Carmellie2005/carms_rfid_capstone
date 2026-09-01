<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Login | SLSU Bontoc Patrol</title>
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
    <body class="font-sans text-slate-900 antialiased dark:text-slate-100">
        <main
            class="relative flex min-h-[100svh] items-center justify-center overflow-hidden bg-[#eef6ff] px-3 py-16 sm:px-4 sm:py-8 dark:bg-slate-950"
            x-data="{ loggingIn: false }"
        >
            <div
                x-show="loggingIn"
                x-cloak
                x-transition.opacity.duration.150ms
                class="fixed inset-0 z-50 flex items-center justify-center bg-white/80 p-4 backdrop-blur-sm dark:bg-slate-950/80"
            >
                <x-brand-spinner class="w-full max-w-sm rounded-xl border border-blue-100 bg-white p-6 text-blue-950 shadow-2xl dark:border-slate-800 dark:bg-slate-900 dark:text-blue-100">
                    Signing in
                    <x-slot name="description">Please wait while your account is being verified.</x-slot>
                </x-brand-spinner>
            </div>

            <a
                href="{{ url('/') }}"
                class="absolute left-5 top-5 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-blue-100 bg-white text-blue-700 shadow-sm transition hover:-translate-x-0.5 hover:border-blue-200 hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:ring-offset-[#eef6ff] dark:border-slate-700 dark:bg-slate-900 dark:text-blue-100 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-950"
                aria-label="Back to homepage"
            >
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                    <path d="M15 18 9 12l6-6" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </a>

            <div class="absolute right-5 top-5">
                <x-theme-toggle />
            </div>

            <section class="w-full max-w-[390px] rounded-xl border border-blue-100 bg-white px-5 py-6 shadow-[0_18px_45px_rgba(15,23,42,0.12)] sm:px-7 dark:border-slate-800 dark:bg-slate-900 dark:shadow-[0_18px_45px_rgba(2,6,23,0.42)]">
                <div class="flex justify-center">
                    <x-application-logo class="h-16 w-16" />
                </div>

                <div class="mt-2 text-center">
                    <h1 class="text-2xl font-bold tracking-normal text-slate-900 dark:text-slate-100">Welcome back</h1>
                    <p class="mt-1.5 text-xs font-medium text-slate-500 dark:text-slate-400">Please log in to continue to your account.</p>
                </div>

                <x-auth-session-status class="mt-5" :status="session('status')" />

                <form method="POST" action="{{ route('login') }}" class="mt-5 space-y-4" x-on:submit="loggingIn = true">
                    @csrf

                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-800 dark:text-slate-200">Email or Username</label>
                        <div class="mt-1.5 flex h-12 items-center rounded-lg border border-slate-200 bg-white px-3.5 shadow-sm transition focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:border-blue-400 dark:focus-within:ring-blue-950">
                            <svg class="mr-3 h-4 w-4 flex-none text-blue-700 dark:text-blue-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path d="M12 12a4.5 4.5 0 1 0 0-9 4.5 4.5 0 0 0 0 9Zm0 2.25c-4.42 0-8 2.47-8 5.5 0 .69.56 1.25 1.25 1.25h13.5c.69 0 1.25-.56 1.25-1.25 0-3.03-3.58-5.5-8-5.5Z" />
                            </svg>
                            <input
                                id="email"
                                class="w-full border-0 bg-transparent text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:ring-0 dark:text-slate-100 dark:placeholder:text-slate-500"
                                type="text"
                                name="email"
                                value="{{ old('email') }}"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="Enter your email or username"
                            >
                        </div>
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>

                    <div>
                        <label for="password" class="block text-xs font-semibold text-slate-800 dark:text-slate-200">Password</label>
                        <div class="mt-1.5 flex h-12 items-center rounded-lg border border-slate-200 bg-white px-3.5 shadow-sm focus-within:border-blue-500 focus-within:ring-2 focus-within:ring-blue-100 dark:border-slate-700 dark:bg-slate-950 dark:focus-within:border-blue-400 dark:focus-within:ring-blue-950">
                            <svg class="mr-3 h-4 w-4 flex-none text-blue-700 dark:text-blue-300" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M7 10V8a5 5 0 0 1 10 0v2h.5A2.5 2.5 0 0 1 20 12.5v6A2.5 2.5 0 0 1 17.5 21h-11A2.5 2.5 0 0 1 4 18.5v-6A2.5 2.5 0 0 1 6.5 10H7Zm2 0h6V8a3 3 0 1 0-6 0v2Zm4 5.73a2 2 0 1 0-2 0V18h2v-2.27Z" clip-rule="evenodd" />
                            </svg>
                            <input
                                id="password"
                                class="w-full border-0 bg-transparent text-sm font-medium text-slate-800 placeholder:text-slate-400 focus:ring-0 dark:text-slate-100 dark:placeholder:text-slate-500"
                                type="password"
                                name="password"
                                required
                                autocomplete="current-password"
                                placeholder="Enter your password"
                            >
                            <button
                                type="button"
                                class="ml-2 inline-flex h-8 w-8 flex-none items-center justify-center rounded-md text-slate-400 transition hover:bg-slate-50 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:text-slate-500 dark:hover:bg-slate-800 dark:hover:text-blue-200"
                                aria-label="Show password"
                                data-password-toggle
                            >
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                    <path d="M2.75 12s3.25-6.25 9.25-6.25S21.25 12 21.25 12 18 18.25 12 18.25 2.75 12 2.75 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                    <path d="M12 14.75a2.75 2.75 0 1 0 0-5.5 2.75 2.75 0 0 0 0 5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                                </svg>
                            </button>
                        </div>
                        <x-input-error :messages="$errors->get('password')" class="mt-2" />
                    </div>

                    <div class="flex items-center justify-between gap-4">
                        <label for="remember_me" class="inline-flex items-center text-xs font-medium text-slate-700 dark:text-slate-300">
                            <input id="remember_me" type="checkbox" class="h-4 w-4 rounded border-slate-300 text-blue-700 shadow-sm focus:ring-blue-500" name="remember" value="1" @checked(old('remember'))>
                            <span class="ms-2">Remember me</span>
                        </label>

                        @if (Route::has('password.request'))
                            <a class="text-xs font-semibold text-blue-700 transition hover:text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:text-blue-300 dark:hover:text-blue-100 dark:focus:ring-offset-slate-900" href="{{ route('password.request') }}">
                                Forgot password?
                            </a>
                        @endif
                    </div>

                    <button
                        type="submit"
                        class="inline-flex h-12 w-full items-center justify-center rounded-lg bg-blue-700 px-5 text-sm font-bold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 disabled:cursor-not-allowed disabled:bg-blue-400"
                        :disabled="loggingIn"
                    >
                        <svg x-show="loggingIn" x-cloak class="mr-2 h-4 w-4 animate-spin" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" d="M4 12a8 8 0 0 1 8-8" stroke="currentColor" stroke-width="4" stroke-linecap="round"></path>
                        </svg>
                        <span x-text="loggingIn ? 'Signing in...' : 'Log in'">Log in</span>
                    </button>

                </form>
            </section>
        </main>

        <script>
            document.querySelector('[data-password-toggle]')?.addEventListener('click', (event) => {
                const input = document.getElementById('password');
                const button = event.currentTarget;
                const isHidden = input.type === 'password';

                input.type = isHidden ? 'text' : 'password';
                button.setAttribute('aria-label', isHidden ? 'Hide password' : 'Show password');
            });
        </script>
    </body>
</html>
