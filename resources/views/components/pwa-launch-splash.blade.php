<div
    x-data="pwaLaunchSplash()"
    x-show="visible"
    x-cloak
    x-transition.opacity.duration.250ms
    class="fixed inset-0 z-[120] flex items-center justify-center bg-white px-6 text-center dark:bg-slate-950"
    role="status"
    aria-live="polite"
>
    <div class="flex flex-col items-center">
        <div class="relative">
            <div class="absolute inset-0 rounded-[2rem] bg-blue-500/20 blur-2xl"></div>
            <img
                src="{{ asset('pwa-icon-192.png') }}?v=slsu-logo-v12"
                alt="SLSU BC Patrol app icon"
                class="relative h-24 w-24 rounded-[1.65rem] border border-blue-100 bg-white object-contain p-2 shadow-2xl shadow-blue-950/15 dark:border-slate-700 dark:bg-slate-900"
            >
        </div>

        <p class="mt-5 text-lg font-bold text-blue-950 dark:text-white">SLSU BC Patrol</p>
        <div class="mt-3 inline-flex items-center gap-2 text-sm font-semibold text-slate-500 dark:text-slate-300">
            <svg class="h-4 w-4 animate-spin text-blue-700 dark:text-blue-300" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="3"></circle>
                <path class="opacity-90" d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" stroke-width="3" stroke-linecap="round"></path>
            </svg>
            <span>Loading...</span>
        </div>
    </div>
</div>
