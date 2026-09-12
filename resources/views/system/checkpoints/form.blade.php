<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">
                    {{ $checkpoint->exists ? 'Edit Checkpoint' : 'New Checkpoint' }}
                </h2>
                <p class="mt-1 text-sm text-blue-600">RFID reader location and device identity</p>
            </div>
            <a href="{{ route('checkpoints.index') }}" class="inline-flex w-full items-center justify-center rounded-md border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 sm:w-auto">
                Back
            </a>
        </div>
    </x-slot>

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ $checkpoint->exists ? route('checkpoints.update', $checkpoint) : route('checkpoints.store') }}" class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-6">
                @csrf
                @if ($checkpoint->exists)
                    @method('PUT')
                @endif

                @include('system.checkpoints.partials.form-fields', [
                    'checkpoint' => $checkpoint,
                    'formContext' => $checkpoint->exists ? 'edit-'.$checkpoint->id : 'create',
                    'fieldPrefix' => 'checkpoint',
                ])

                <div class="mt-6 flex justify-stretch sm:justify-end">
                    <x-primary-button class="w-full justify-center sm:w-auto">{{ $checkpoint->exists ? 'Save Changes' : 'Create Checkpoint' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
