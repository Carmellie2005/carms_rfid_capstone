@props([
    'id',
    'name',
    'placeholder' => '',
    'autocomplete' => null,
    'required' => false,
    'autofocus' => false,
    'showLabel' => 'Show password',
    'hideLabel' => 'Hide password',
])

<div x-data="{ showPassword: false }" class="relative mt-1">
    <input
        id="{{ $id }}"
        name="{{ $name }}"
        type="password"
        :type="showPassword ? 'text' : 'password'"
        placeholder="{{ $placeholder }}"
        @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
        @required($required)
        @autofocus($autofocus)
        {{ $attributes->merge(['class' => 'block w-full rounded-md border-slate-300 pr-11 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-slate-700 dark:bg-slate-950 dark:text-slate-100 dark:placeholder-slate-500']) }}
    >
    <button
        type="button"
        x-on:click="showPassword = ! showPassword"
        aria-label="{{ $showLabel }}"
        title="{{ $showLabel }}"
        :aria-label="showPassword ? @js($hideLabel) : @js($showLabel)"
        :title="showPassword ? @js($hideLabel) : @js($showLabel)"
        class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-md text-slate-400 transition hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500 dark:text-slate-300 dark:hover:text-white"
    >
        <svg x-show="! showPassword" class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M2.75 12s3.25-6.25 9.25-6.25S21.25 12 21.25 12 18 18.25 12 18.25 2.75 12 2.75 12Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M12 14.75a2.75 2.75 0 1 0 0-5.5 2.75 2.75 0 0 0 0 5.5Z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
        <svg x-show="showPassword" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="m3.5 3.5 17 17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
            <path d="M9.88 9.88a3 3 0 0 0 4.24 4.24" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M7.12 7.36C4.28 8.84 2.75 12 2.75 12s3.25 6.25 9.25 6.25c1.52 0 2.86-.4 4.03-1.01" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
            <path d="M12 5.75c6 0 9.25 6.25 9.25 6.25a15 15 0 0 1-2.18 2.98" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" />
        </svg>
    </button>
</div>
