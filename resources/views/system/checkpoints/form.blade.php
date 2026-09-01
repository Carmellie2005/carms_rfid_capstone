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

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="code" class="block text-sm font-medium text-slate-700">Checkpoint Code</label>
                        <input id="code" name="code" value="{{ old('code', $checkpoint->code) }}" class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700">Checkpoint Name</label>
                        <input id="name" name="name" value="{{ old('name', $checkpoint->name) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <label for="location" class="block text-sm font-medium text-slate-700">Location</label>
                        <input id="location" name="location" value="{{ old('location', $checkpoint->location) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <x-input-error :messages="$errors->get('location')" class="mt-2" />
                    </div>
                    <div>
                        <label for="device_uid" class="block text-sm font-medium text-slate-700">Device UID</label>
                        <input id="device_uid" name="device_uid" value="{{ old('device_uid', $checkpoint->device_uid) }}" class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <x-input-error :messages="$errors->get('device_uid')" class="mt-2" />
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="active" @selected(old('status', $checkpoint->status) === 'active')>Active</option>
                            <option value="inactive" @selected(old('status', $checkpoint->status) === 'inactive')>Inactive</option>
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>
                    <div class="md:col-span-2">
                        <label for="description" class="block text-sm font-medium text-slate-700">Description</label>
                        <textarea id="description" name="description" rows="4" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $checkpoint->description) }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-stretch sm:justify-end">
                    <x-primary-button class="w-full justify-center sm:w-auto">{{ $checkpoint->exists ? 'Save Changes' : 'Create Checkpoint' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
