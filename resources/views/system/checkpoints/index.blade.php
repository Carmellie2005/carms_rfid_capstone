<x-app-layout>
    @php
        $checkpointFormContext = old('_checkpoint_form');
        $createPanelOpen = $errors->any() && ($checkpointFormContext === 'create' || blank($checkpointFormContext));
        $editPanelCheckpointId = $errors->any() && \Illuminate\Support\Str::startsWith((string) $checkpointFormContext, 'edit-')
            ? \Illuminate\Support\Str::after($checkpointFormContext, 'edit-')
            : '';
    @endphp

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
        x-data="checkpointManagementPage({
            createModalOpen: @js($createPanelOpen),
            editModalOpen: @js(filled($editPanelCheckpointId)),
            editCheckpointId: @js((string) $editPanelCheckpointId),
        })"
        x-on:open-create-checkpoint.window="openCreateCheckpointModal()"
    >
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800">{{ session('status') }}</div>
            @endif

            <div
                class="transition-[grid-template-columns] duration-300 ease-out lg:grid lg:items-start"
                :class="sidePanelOpen() ? 'lg:grid-cols-[minmax(0,1fr)_minmax(22rem,26rem)] lg:gap-4' : 'lg:grid-cols-[minmax(0,1fr)_0rem] lg:gap-0'"
            >
                <div
                    class="min-w-0 space-y-5 transition-transform duration-300 ease-out"
                    :class="sidePanelOpen() ? 'lg:-translate-x-2' : 'lg:translate-x-0'"
                >
            <div class="grid grid-cols-2 gap-3 lg:hidden">
                @forelse ($checkpoints as $checkpoint)
                    <article class="min-w-0 rounded-md border border-blue-100 bg-white p-3 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <h3 class="truncate text-sm font-semibold text-blue-950">{{ $checkpoint->name }}</h3>
                                <p class="mt-1 font-mono text-xs text-slate-500">{{ $checkpoint->code }}</p>
                            </div>
                            <span class="shrink-0 whitespace-nowrap rounded-md px-2 py-1 text-[0.65rem] font-semibold ring-1 {{ $checkpoint->status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/35 dark:text-emerald-200 dark:ring-emerald-400/45' : 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-slate-950/50 dark:text-slate-200 dark:ring-slate-500/60' }}">
                                {{ ucfirst($checkpoint->status) }}
                            </span>
                        </div>

                        <dl class="mt-3 grid gap-2 text-xs text-slate-600">
                            <div class="min-w-0">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800">Location</dt>
                                <dd class="mt-1 truncate">{{ $checkpoint->location }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800">Device UID</dt>
                                <dd class="mt-1 truncate font-mono">{{ $checkpoint->device_uid ?? 'None' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button type="button" data-edit-checkpoint-id="{{ $checkpoint->id }}" x-on:click="openEditCheckpointModal($event.currentTarget.dataset.editCheckpointId)" class="inline-flex h-9 items-center justify-center whitespace-nowrap rounded-md border border-blue-200 px-2 text-xs font-semibold text-blue-700 hover:bg-blue-50">Edit</button>
                            <button type="button" data-delete-action="{{ route('checkpoints.destroy', $checkpoint) }}" data-delete-name="{{ $checkpoint->name }}" x-on:click="openDeleteCheckpointModal($event.currentTarget.dataset.deleteAction, $event.currentTarget.dataset.deleteName)" class="inline-flex h-9 w-full items-center justify-center whitespace-nowrap rounded-md border border-red-200 px-2 text-xs font-semibold text-red-700 hover:bg-red-50">Delete</button>
                        </div>
                    </article>
                @empty
                    <div class="col-span-2 rounded-md border border-blue-100 bg-white px-5 py-8 text-center text-slate-500 shadow-sm">No checkpoints registered.</div>
                @endforelse
            </div>

            <div
                class="hidden rounded-md border border-blue-100 bg-white shadow-sm lg:block"
                :class="sidePanelOpen() ? 'lg:h-[32rem] lg:max-h-[calc(100vh-12rem)] lg:overflow-auto' : 'overflow-hidden'"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-extrabold uppercase text-blue-800">
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
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $checkpoint->status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200 dark:bg-emerald-950/35 dark:text-emerald-200 dark:ring-emerald-400/45' : 'bg-slate-50 text-slate-600 ring-slate-200 dark:bg-slate-950/50 dark:text-slate-200 dark:ring-slate-500/60' }}">
                                            {{ ucfirst($checkpoint->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" data-edit-checkpoint-id="{{ $checkpoint->id }}" x-on:click="openEditCheckpointModal($event.currentTarget.dataset.editCheckpointId)" class="rounded-md border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50">Edit</button>
                                            <button type="button" data-delete-action="{{ route('checkpoints.destroy', $checkpoint) }}" data-delete-name="{{ $checkpoint->name }}" x-on:click="openDeleteCheckpointModal($event.currentTarget.dataset.deleteAction, $event.currentTarget.dataset.deleteName)" class="rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">Delete</button>
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
                </div>

            <div
                x-show="createModalOpen"
                x-cloak
                x-transition.opacity.duration.150ms
                x-on:click.self="closeCreateCheckpointModal()"
                x-on:keydown.escape.window="closeCreateCheckpointModal()"
                class="fixed inset-0 z-[80] flex items-stretch justify-end overflow-hidden bg-slate-950/40 p-0 lg:static lg:z-auto lg:block lg:bg-transparent lg:p-0"
            >
                <section
                    x-show="createModalOpen"
                    role="dialog"
                    x-bind:aria-modal="isCompactPanelViewport() ? 'true' : 'false'"
                    aria-labelledby="create-checkpoint-title"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="ml-auto flex h-full w-full max-w-2xl flex-col overflow-hidden bg-white shadow-xl sm:rounded-l-lg lg:ml-0 lg:h-[32rem] lg:max-h-[calc(100vh-12rem)] lg:max-w-none lg:rounded-md lg:border lg:border-blue-100 lg:shadow-sm"
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
                            @include('system.checkpoints.partials.form-fields', [
                                'checkpoint' => $newCheckpoint,
                                'formContext' => 'create',
                                'fieldPrefix' => 'create_checkpoint',
                            ])
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

            @foreach ($checkpoints as $checkpoint)
                @php
                    $editCheckpointFormContext = 'edit-'.$checkpoint->id;
                @endphp

                <div
                    x-show="editModalOpen && editCheckpointId === '{{ $checkpoint->id }}'"
                    x-cloak
                    x-transition.opacity.duration.150ms
                    x-on:click.self="closeEditCheckpointModal()"
                    x-on:keydown.escape.window="closeEditCheckpointModal()"
                    class="fixed inset-0 z-[80] flex items-stretch justify-end overflow-hidden bg-slate-950/40 p-0 lg:static lg:z-auto lg:block lg:bg-transparent lg:p-0"
                >
                    <section
                        x-show="editModalOpen && editCheckpointId === '{{ $checkpoint->id }}'"
                        role="dialog"
                        x-bind:aria-modal="isCompactPanelViewport() ? 'true' : 'false'"
                        aria-labelledby="edit-checkpoint-title-{{ $checkpoint->id }}"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="translate-x-full"
                        x-transition:enter-end="translate-x-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="translate-x-full"
                        class="ml-auto flex h-full w-full max-w-2xl flex-col overflow-hidden bg-white shadow-xl sm:rounded-l-lg lg:ml-0 lg:h-[32rem] lg:max-h-[calc(100vh-12rem)] lg:max-w-none lg:rounded-md lg:border lg:border-blue-100 lg:shadow-sm"
                    >
                        <header class="flex items-start justify-between gap-4 border-b border-blue-100 px-5 py-4">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Checkpoint</p>
                                <h3 id="edit-checkpoint-title-{{ $checkpoint->id }}" class="mt-1 truncate text-lg font-semibold text-blue-950">Edit Checkpoint</h3>
                                <p class="mt-1 truncate text-sm text-slate-500">{{ $checkpoint->name }} - {{ $checkpoint->code }}</p>
                            </div>
                            <button
                                type="button"
                                x-on:click="closeEditCheckpointModal()"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-blue-100 text-slate-500 transition hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                aria-label="Close edit checkpoint form"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.22 5.22a.75.75 0 0 1 1.06 0L10 8.94l3.72-3.72a.75.75 0 1 1 1.06 1.06L11.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06L10 11.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06L8.94 10 5.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </header>

                        <form method="POST" action="{{ route('checkpoints.update', $checkpoint) }}" class="flex min-h-0 flex-1 flex-col">
                            @csrf
                            @method('PUT')

                            <div class="mobile-scroll-area flex-1 overflow-y-auto px-5 py-5">
                                @include('system.checkpoints.partials.form-fields', [
                                    'checkpoint' => $checkpoint,
                                    'formContext' => $editCheckpointFormContext,
                                    'fieldPrefix' => 'edit_checkpoint_'.$checkpoint->id,
                                ])
                            </div>

                            <footer class="flex flex-col-reverse gap-2 border-t border-blue-100 px-5 py-4 sm:flex-row sm:justify-end">
                                <button type="button" x-on:click="closeEditCheckpointModal()" class="inline-flex h-10 items-center justify-center rounded-md border border-blue-200 bg-white px-4 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    Cancel
                                </button>
                                <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                    Save Changes
                                </button>
                            </footer>
                        </form>
                    </section>
                </div>
            @endforeach
            </div>

            <div
                x-show="deleteModalOpen"
                x-cloak
                x-transition.opacity.duration.200ms
                x-on:click.self="closeDeleteCheckpointModal()"
                x-on:keydown.escape.window="closeDeleteCheckpointModal()"
                class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/60 p-4"
            >
                <section
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="delete-checkpoint-title"
                    class="w-full max-w-md overflow-hidden rounded-lg bg-white shadow-2xl"
                >
                    <div class="border-b border-red-100 px-5 py-4">
                        <h3 id="delete-checkpoint-title" class="text-lg font-semibold text-red-700">Delete Checkpoint</h3>
                        <p class="mt-1 text-sm text-slate-500">This will remove the checkpoint profile and linked reader reference.</p>
                    </div>

                    <div class="px-5 py-5">
                        <p class="text-sm text-slate-700">
                            Are you sure you want to delete
                            <span class="font-semibold text-slate-950" x-text="deleteCheckpointName"></span>?
                        </p>
                    </div>

                    <form method="POST" x-bind:action="deleteCheckpointAction" class="flex flex-col-reverse gap-2 border-t border-red-100 px-5 py-4 sm:flex-row sm:justify-end">
                        @csrf
                        @method('DELETE')
                        <button type="button" x-ref="deleteCheckpointCancelButton" x-on:click="closeDeleteCheckpointModal()" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-red-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            Delete Checkpoint
                        </button>
                    </form>
                </section>
            </div>

            {{ $checkpoints->links() }}
        </div>
    </div>
</x-app-layout>
