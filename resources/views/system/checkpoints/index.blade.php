<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">{{ __('Checkpoint Management') }}</h2>
                <p class="mt-1 text-sm text-blue-600">Campus patrol locations and RFID reader devices</p>
            </div>
            <button
                type="button"
                x-on:click="$dispatch('open-create-checkpoint')"
                class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                New Checkpoint
            </button>
        </div>
    </x-slot>

    <div
        class="py-5 sm:py-8"
        x-data="checkpointManagementPage({ createModalOpen: @js($errors->any()) })"
        x-on:open-create-checkpoint.window="openCreateCheckpointModal()"
    >
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800">{{ session('status') }}</div>
            @endif

            <div class="grid gap-3 md:hidden">
                @forelse ($checkpoints as $checkpoint)
                    <article class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h3 class="truncate font-semibold text-blue-950">{{ $checkpoint->name }}</h3>
                                <p class="mt-1 font-mono text-xs text-slate-500">{{ $checkpoint->code }}</p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $checkpoint->status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-50 text-slate-600 ring-slate-200' }}">
                                {{ ucfirst($checkpoint->status) }}
                            </span>
                        </div>

                        <dl class="mt-4 grid gap-3 text-sm text-slate-600">
                            <div>
                                <dt class="text-xs font-semibold uppercase text-blue-800">Location</dt>
                                <dd class="mt-1">{{ $checkpoint->location }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs font-semibold uppercase text-blue-800">Device UID</dt>
                                <dd class="mt-1 font-mono">{{ $checkpoint->device_uid ?? 'None' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-4 grid grid-cols-2 gap-2">
                            <a href="{{ route('checkpoints.edit', $checkpoint) }}" class="inline-flex h-10 items-center justify-center rounded-md border border-blue-200 px-3 text-sm font-semibold text-blue-700 hover:bg-blue-50">Edit</a>
                            <form method="POST" action="{{ route('checkpoints.destroy', $checkpoint) }}" onsubmit="return confirm('Remove this checkpoint?')">
                                @csrf
                                @method('DELETE')
                                <button class="inline-flex h-10 w-full items-center justify-center rounded-md border border-red-200 px-3 text-sm font-semibold text-red-700 hover:bg-red-50" type="submit">Delete</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="rounded-lg border border-blue-100 bg-white px-5 py-8 text-center text-slate-500 shadow-sm">No checkpoints registered.</div>
                @endforelse
            </div>

            <div class="hidden overflow-hidden rounded-lg border border-blue-100 bg-white shadow-sm md:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-semibold uppercase text-blue-800">
                            <tr>
                                <th class="px-5 py-3">Checkpoint</th>
                                <th class="px-5 py-3">Location</th>
                                <th class="px-5 py-3">Device UID</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            @forelse ($checkpoints as $checkpoint)
                                <tr>
                                    <td class="px-5 py-4">
                                        <div class="font-medium text-slate-900">{{ $checkpoint->name }}</div>
                                        <div class="text-xs font-mono text-slate-500">{{ $checkpoint->code }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-slate-600">{{ $checkpoint->location }}</td>
                                    <td class="px-5 py-4 font-mono text-slate-700">{{ $checkpoint->device_uid ?? 'None' }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 {{ $checkpoint->status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-50 text-slate-600 ring-slate-200' }}">
                                            {{ ucfirst($checkpoint->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('checkpoints.edit', $checkpoint) }}" class="rounded-md border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50">Edit</a>
                                            <form method="POST" action="{{ route('checkpoints.destroy', $checkpoint) }}" onsubmit="return confirm('Remove this checkpoint?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-5 py-8 text-center text-slate-500">No checkpoints registered.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                x-show="createModalOpen"
                x-cloak
                x-transition.opacity.duration.200ms
                x-on:click.self="closeCreateCheckpointModal()"
                x-on:keydown.escape.window="closeCreateCheckpointModal()"
                class="fixed inset-0 z-[80] flex items-stretch justify-center overflow-y-auto bg-slate-950/60 p-0 sm:items-center sm:px-4 sm:py-6"
            >
                <section
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="create-checkpoint-title"
                    class="flex max-h-screen w-full flex-col overflow-hidden bg-white shadow-xl sm:max-h-[90vh] sm:max-w-2xl sm:rounded-lg"
                >
                    <header class="flex items-start justify-between gap-4 border-b border-blue-100 px-5 py-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Checkpoint</p>
                            <h3 id="create-checkpoint-title" class="mt-1 text-lg font-semibold text-blue-950">New Checkpoint</h3>
                        </div>
                        <button
                            type="button"
                            x-on:click="closeCreateCheckpointModal()"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-blue-100 text-slate-500 transition hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            aria-label="Close new checkpoint form"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.22 5.22a.75.75 0 0 1 1.06 0L10 8.94l3.72-3.72a.75.75 0 1 1 1.06 1.06L11.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06L10 11.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06L8.94 10 5.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </header>

                    <form method="POST" action="{{ route('checkpoints.store') }}" class="flex min-h-0 flex-1 flex-col">
                        @csrf

                        <div class="mobile-scroll-area flex-1 overflow-y-auto px-5 py-5">
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label for="create_code" class="block text-sm font-medium text-slate-700">Checkpoint Code</label>
                                    <input id="create_code" x-ref="createCheckpointFirstField" name="code" value="{{ old('code', $newCheckpoint->code) }}" class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    <x-input-error :messages="$errors->get('code')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="create_name" class="block text-sm font-medium text-slate-700">Checkpoint Name</label>
                                    <input id="create_name" name="name" value="{{ old('name', $newCheckpoint->name) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="create_location" class="block text-sm font-medium text-slate-700">Location</label>
                                    <input id="create_location" name="location" value="{{ old('location', $newCheckpoint->location) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    <x-input-error :messages="$errors->get('location')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="create_device_uid" class="block text-sm font-medium text-slate-700">Device UID</label>
                                    <input id="create_device_uid" name="device_uid" value="{{ old('device_uid', $newCheckpoint->device_uid) }}" class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <x-input-error :messages="$errors->get('device_uid')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="create_status" class="block text-sm font-medium text-slate-700">Status</label>
                                    <select id="create_status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="active" @selected(old('status', $newCheckpoint->status) === 'active')>Active</option>
                                        <option value="inactive" @selected(old('status', $newCheckpoint->status) === 'inactive')>Inactive</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                                </div>
                                <div class="md:col-span-2">
                                    <label for="create_description" class="block text-sm font-medium text-slate-700">Description</label>
                                    <textarea id="create_description" name="description" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('description', $newCheckpoint->description) }}</textarea>
                                    <x-input-error :messages="$errors->get('description')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <footer class="flex flex-col-reverse gap-2 border-t border-blue-100 px-5 py-4 sm:flex-row sm:justify-end">
                            <button type="button" x-on:click="closeCreateCheckpointModal()" class="inline-flex h-10 items-center justify-center rounded-md border border-blue-200 bg-white px-4 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Cancel
                            </button>
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Create Checkpoint
                            </button>
                        </footer>
                    </form>
                </section>
            </div>

            {{ $checkpoints->links() }}
        </div>
    </div>
</x-app-layout>
