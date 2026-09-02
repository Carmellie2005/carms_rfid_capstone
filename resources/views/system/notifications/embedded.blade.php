<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Notifications</title>

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

        @vite(['resources/css/app.css'])
    </head>
    <body class="h-full bg-blue-50/60 font-sans antialiased dark:bg-slate-950">
        <main class="mobile-scroll-area h-full overflow-y-auto p-3 sm:p-4">
            <div class="space-y-4">
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}" class="flex justify-end">
                        @csrf
                        <button type="submit" class="inline-flex h-9 items-center justify-center whitespace-nowrap rounded-md bg-blue-700 px-3 text-xs font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-slate-950">
                            Mark all read
                        </button>
                    </form>
                @endif

                @include('system.notifications.partials.feed')
            </div>
        </main>
    </body>
</html>
