<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950 dark:text-white">
                    {{ $guard->exists ? 'Edit Guard' : 'New Guard' }}
                </h2>
                <p class="mt-1 text-sm text-blue-600 dark:text-slate-200">Guard identity, RFID card, shift, and login account</p>
            </div>
            <a href="{{ route('guards.index') }}" class="inline-flex w-full items-center justify-center rounded-md border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 dark:border-slate-700 dark:bg-slate-900 dark:text-white dark:hover:bg-slate-800 sm:w-auto">
                Back
            </a>
        </div>
    </x-slot>

    @php
        $passwordRequired = ! $guard->exists || ! $guard->user_id;
    @endphp

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form
                method="POST"
                action="{{ $guard->exists ? route('guards.update', $guard) : route('guards.store') }}"
                class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm dark:border-slate-700 dark:bg-slate-900 sm:p-6"
            >
                @csrf
                @if ($guard->exists)
                    @method('PUT')
                @endif

                @include('system.guards.partials.form-fields', [
                    'guard' => $guard,
                    'passwordRequired' => $passwordRequired,
                    'formContext' => $guard->exists ? 'edit-'.$guard->id : 'create',
                    'fieldPrefix' => 'guard',
                ])

                <div class="mt-6 flex justify-stretch sm:justify-end">
                    <x-primary-button class="w-full justify-center sm:w-auto">{{ $guard->exists ? 'Save Changes' : 'Create Guard Account' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
