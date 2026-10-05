<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Something went wrong</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950 text-white">
    <main class="flex min-h-screen items-center justify-center px-6 py-12">
        <section class="w-full max-w-lg rounded-lg border border-white/10 bg-white p-6 text-center text-slate-900 shadow-2xl">
            <p class="text-sm font-semibold uppercase tracking-wide text-blue-700">Server Error</p>
            <h1 class="mt-3 text-2xl font-bold text-blue-950">Something went wrong while saving the record.</h1>
            <p class="mt-3 text-sm leading-6 text-slate-600">
                Please go back and try again. If the same message appears, contact the supervisor or system administrator.
            </p>
            <div class="mt-6 flex flex-col gap-2 sm:flex-row sm:justify-center">
                <a href="{{ url()->previous() }}" class="inline-flex h-11 items-center justify-center rounded-md border border-blue-200 px-5 text-sm font-semibold text-blue-700 hover:bg-blue-50">
                    Go Back
                </a>
                <a href="{{ route('dashboard') }}" class="inline-flex h-11 items-center justify-center rounded-md bg-blue-700 px-5 text-sm font-semibold text-white hover:bg-blue-800">
                    Open Dashboard
                </a>
            </div>
        </section>
    </main>
</body>
</html>
