<div
    x-data="{ open: false }"
    x-on:open-logout-confirmation.window="open = true"
    x-on:keydown.escape.window="open = false"
    x-show="open"
    x-cloak
    x-transition.opacity.duration.150ms
    class="fixed inset-0 z-[120] flex items-center justify-center bg-slate-950/45 p-4 backdrop-blur-sm"
    role="dialog"
    aria-modal="true"
    aria-labelledby="logout-confirmation-title"
    x-on:click.self="open = false"
>
    <section
        x-show="open"
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2 scale-[0.98]"
        x-transition:enter-end="opacity-100 translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 translate-y-2 scale-[0.98]"
        class="w-full max-w-sm rounded-lg border border-blue-100 bg-white p-5 shadow-2xl dark:border-slate-700 dark:bg-slate-900"
    >
        <div class="flex items-start gap-3">
            <span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-300" aria-hidden="true">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none">
                    <path d="M10 17l5-5-5-5M15 12H3m9-7h6a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-6" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
                </svg>
            </span>
            <div>
                <h2 id="logout-confirmation-title" class="text-base font-semibold text-blue-950 dark:text-white">Log out?</h2>
                <p class="mt-1 text-sm leading-5 text-slate-600 dark:text-slate-300">Are you sure you want to log out of SLSU BC Patrol?</p>
            </div>
        </div>

        <div class="mt-5 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
            <button
                type="button"
                x-on:click="open = false"
                class="inline-flex h-10 items-center justify-center rounded-md border border-blue-200 px-4 text-sm font-semibold text-blue-800 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-800 dark:focus:ring-offset-slate-900"
            >
                Cancel
            </button>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button
                    type="submit"
                    class="inline-flex h-10 w-full items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto dark:bg-blue-600 dark:hover:bg-blue-500 dark:focus:ring-offset-slate-900"
                >
                    Log Out
                </button>
            </form>
        </div>
    </section>
</div>
