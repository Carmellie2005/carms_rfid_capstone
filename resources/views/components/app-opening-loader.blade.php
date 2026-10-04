@props([
    'message' => 'Initializing Patrol System...',
])

<div
    data-app-opening-loader
    class="app-opening-loader fixed inset-0 z-[120] flex items-center justify-center overflow-hidden bg-[#f6fbff] px-6 text-blue-950 dark:bg-slate-950 dark:text-blue-50"
    role="status"
    aria-live="polite"
>
    <div class="flex w-full max-w-sm flex-col items-center text-center">
        <div class="app-opening-loader-emblem" aria-hidden="true">
            <span class="app-opening-loader-ring app-opening-loader-ring-soft"></span>
            <span class="app-opening-loader-ring app-opening-loader-ring-main"></span>
            <span class="app-opening-loader-ring app-opening-loader-ring-offset"></span>
            <span class="app-opening-loader-orb app-opening-loader-orb-top"></span>
            <span class="app-opening-loader-orb app-opening-loader-orb-bottom"></span>
            <span class="app-opening-loader-logo">
                <x-application-logo class="h-32 w-32" />
            </span>
        </div>

        <h1 class="mt-8 text-4xl font-extrabold tracking-normal text-blue-950 dark:text-white">
            SLSU-BC <span class="text-blue-600 dark:text-sky-300">PATROL</span>
        </h1>
        <p class="mt-4 text-base font-medium text-blue-950/80 dark:text-blue-100/80">{{ $message }}</p>

        <span class="app-opening-loader-dots mt-7" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </span>
    </div>
</div>

@once
    <script>
        (() => {
            const loaders = document.querySelectorAll('[data-app-opening-loader]');
            const key = 'slsuBcPatrolOpeningLoaderShown';
            let alreadyShown = false;

            try {
                alreadyShown = sessionStorage.getItem(key) === '1';
                sessionStorage.setItem(key, '1');
            } catch (error) {
                alreadyShown = false;
            }

            const hideLoader = (loader, immediate = false) => {
                if (! loader) {
                    return;
                }

                if (immediate) {
                    loader.remove();
                    return;
                }

                loader.classList.add('app-opening-loader-hidden');
                window.setTimeout(() => loader.remove(), 420);
            };

            loaders.forEach((loader) => {
                if (alreadyShown) {
                    hideLoader(loader, true);
                    return;
                }

                const minimumDuration = window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 250 : 950;

                window.setTimeout(() => {
                    if (document.readyState === 'complete') {
                        hideLoader(loader);
                        return;
                    }

                    window.addEventListener('load', () => hideLoader(loader), { once: true });
                }, minimumDuration);
            });
        })();
    </script>
@endonce
