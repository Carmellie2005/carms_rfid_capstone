<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
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
    <body class="font-sans text-gray-900 antialiased dark:text-slate-100">
        <div class="flex min-h-screen flex-col items-center bg-blue-50 px-4 pt-6 sm:justify-center sm:pt-0 dark:bg-slate-950">
            <div class="absolute right-4 top-4">
                <x-theme-toggle />
            </div>

            <div>
                <a href="/">
                    <x-application-logo class="w-20 h-20" />
                </a>
            </div>

            <div class="mt-6 w-full overflow-hidden border border-blue-100 bg-white px-6 py-5 shadow-sm sm:max-w-md sm:rounded-lg dark:border-slate-800 dark:bg-slate-900">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
