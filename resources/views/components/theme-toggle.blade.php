@props([
    'variant' => 'default',
])

@php
    $buttonClasses = $variant === 'header'
        ? 'inline-flex h-12 w-12 items-center justify-center rounded-full border-0 bg-blue-50 text-blue-950 shadow-sm transition hover:bg-blue-100 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:bg-slate-800 dark:text-blue-100 dark:hover:bg-slate-700 dark:focus:ring-offset-slate-950'
        : 'inline-flex h-10 w-10 items-center justify-center rounded-md border border-blue-100 bg-white text-blue-900 shadow-sm transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:border-slate-700 dark:bg-slate-900 dark:text-blue-100 dark:hover:bg-slate-800';
@endphp

<button
    type="button"
    x-data="{
        darkMode: document.documentElement.classList.contains('dark'),
        toggleTheme() {
            this.darkMode = ! this.darkMode;
            document.documentElement.classList.toggle('dark', this.darkMode);
            localStorage.setItem('theme', this.darkMode ? 'dark' : 'light');
            window.dispatchEvent(new CustomEvent('theme-changed', { detail: this.darkMode }));
        }
    }"
    x-init="window.addEventListener('theme-changed', (event) => darkMode = event.detail)"
    @click="toggleTheme()"
    {{ $attributes->merge(['class' => $buttonClasses]) }}
    aria-label="Toggle dark mode"
>
    <svg x-show="! darkMode" class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path fill-rule="evenodd" d="M7.455 2.004a.75.75 0 0 1 .26.77A7 7 0 0 0 16.87 10.7a.75.75 0 0 1 .927.974A8.5 8.5 0 1 1 6.527 1.75a.75.75 0 0 1 .928.254Z" clip-rule="evenodd" />
    </svg>
    <svg x-show="darkMode" x-cloak class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
        <path d="M10 2a.75.75 0 0 1 .75.75v1a.75.75 0 0 1-1.5 0v-1A.75.75 0 0 1 10 2ZM10 15.5a.75.75 0 0 1 .75.75v1a.75.75 0 0 1-1.5 0v-1a.75.75 0 0 1 .75-.75ZM17.25 9.25a.75.75 0 0 1 0 1.5h-1a.75.75 0 0 1 0-1.5h1ZM3.75 9.25a.75.75 0 0 1 0 1.5h-1a.75.75 0 0 1 0-1.5h1ZM15.48 4.52a.75.75 0 0 1 0 1.06l-.7.7a.75.75 0 0 1-1.06-1.06l.7-.7a.75.75 0 0 1 1.06 0ZM6.28 13.72a.75.75 0 0 1 0 1.06l-.7.7a.75.75 0 0 1-1.06-1.06l.7-.7a.75.75 0 0 1 1.06 0ZM15.48 15.48a.75.75 0 0 1-1.06 0l-.7-.7a.75.75 0 0 1 1.06-1.06l.7.7a.75.75 0 0 1 0 1.06ZM6.28 6.28a.75.75 0 0 1-1.06 0l-.7-.7a.75.75 0 1 1 1.06-1.06l.7.7a.75.75 0 0 1 0 1.06ZM10 6a4 4 0 1 1 0 8 4 4 0 0 1 0-8Z" />
    </svg>
</button>
