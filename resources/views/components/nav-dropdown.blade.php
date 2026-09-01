@props(['label', 'active' => false])

@php
    $triggerClasses = $active
        ? 'inline-flex items-center gap-1 border-b-2 border-blue-600 px-1 pt-1 text-sm font-medium leading-5 text-blue-900 transition duration-150 ease-in-out focus:outline-none focus:border-blue-700'
        : 'inline-flex items-center gap-1 border-b-2 border-transparent px-1 pt-1 text-sm font-medium leading-5 text-slate-500 transition duration-150 ease-in-out hover:border-blue-200 hover:text-blue-800 focus:outline-none focus:border-blue-300 focus:text-blue-800';
@endphp

<x-dropdown align="left" width="64" contentClasses="bg-white py-2">
    <x-slot name="trigger">
        <button type="button" class="{{ $triggerClasses }}">
            <span>{{ $label }}</span>
            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
            </svg>
        </button>
    </x-slot>

    <x-slot name="content">
        {{ $slot }}
    </x-slot>
</x-dropdown>
