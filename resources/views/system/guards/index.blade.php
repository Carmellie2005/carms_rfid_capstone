<x-app-layout>
    @php
        $faceVerificationEnabled = \App\Support\FaceVerification::enabled();
        $guardFormContext = old('_guard_form');
        $createDrawerOpen = $errors->any() && ($guardFormContext === 'create' || blank($guardFormContext));
        $editDrawerGuardId = $errors->any() && \Illuminate\Support\Str::startsWith((string) $guardFormContext, 'edit-')
            ? \Illuminate\Support\Str::after($guardFormContext, 'edit-')
            : '';
    @endphp

    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">{{ __('Guard Management') }}</h2>
                <p class="mt-1 text-sm text-blue-600">{{ $faceVerificationEnabled ? 'Registered guards, RFID cards, and live face registration status' : 'Registered guards, RFID cards, shifts, and login accounts' }}</p>
            </div>
            <button
                type="button"
                x-on:click="$dispatch('open-create-guard')"
                class="inline-flex w-full items-center justify-center gap-2 rounded-md bg-blue-700 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto"
            >
                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                    <path d="M10 4v12M4 10h12" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                </svg>
                New Guard
            </button>
        </div>
    </x-slot>

    <div
        class="py-5 sm:py-8"
        x-data="guardManagementPage({
            createModalOpen: @js($createDrawerOpen),
            editModalOpen: @js(filled($editDrawerGuardId)),
            editGuardId: @js((string) $editDrawerGuardId),
        })"
        x-on:open-create-guard.window="openCreateGuardModal()"
    >
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800">{{ session('status') }}</div>
            @endif

            <div
                class="transition-[grid-template-columns] duration-300 ease-out lg:grid lg:items-start"
                :class="sidePanelOpen() ? 'lg:grid-cols-[minmax(0,1fr)_minmax(24rem,28rem)] lg:gap-4' : 'lg:grid-cols-[minmax(0,1fr)_0rem] lg:gap-0'"
            >
                <div
                    class="min-w-0 space-y-5 transition-transform duration-300 ease-out"
                    :class="sidePanelOpen() ? 'lg:-translate-x-2' : 'lg:translate-x-0'"
                >
            <div class="grid grid-cols-2 gap-3 lg:hidden">
                @forelse ($guards as $guard)
                    @php
                        $hasLiveFaceRegistration = \App\Support\FaceVerification::hasCompleteRegistration($guard->faceDescriptors);
                    @endphp
                    <article class="min-w-0 rounded-md border border-blue-100 bg-white p-3 shadow-sm">
                        <div class="flex items-start justify-between gap-2">
                            <div class="min-w-0">
                                <button
                                    type="button"
                                    data-records-url="{{ route('guards.records', $guard) }}"
                                    x-on:click="openGuardRecord($event.currentTarget.dataset.recordsUrl)"
                                    class="block max-w-full truncate text-left text-sm font-semibold text-blue-950 transition hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                >
                                    {{ $guard->name }}
                                </button>
                                <p class="mt-1 truncate text-xs text-slate-500">{{ $guard->employee_no }}</p>
                            </div>
                            <span class="shrink-0 whitespace-nowrap rounded-md px-2 py-1 text-[0.65rem] font-semibold ring-1 {{ $guard->status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-50 text-slate-600 ring-slate-200' }}">
                                {{ ucfirst($guard->status) }}
                            </span>
                        </div>

                        <dl class="mt-3 grid gap-2 text-xs text-slate-600">
                            <div class="min-w-0">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800">Account</dt>
                                <dd class="mt-1 truncate font-mono font-semibold text-blue-900">{{ $guard->user?->username ?? 'No account' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800">RFID UID</dt>
                                <dd class="mt-1 truncate font-mono">{{ $guard->rfid_uid }}</dd>
                            </div>
                            @if ($faceVerificationEnabled)
                            <div>
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800">Face Registration</dt>
                                <dd class="mt-1 whitespace-nowrap">{{ $hasLiveFaceRegistration ? 'Registered' : 'Not registered' }}</dd>
                            </div>
                            @endif
                            <div class="min-w-0">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800">Contact</dt>
                                <dd class="mt-1 truncate">{{ $guard->email ?? 'No email' }}</dd>
                                <dd class="truncate text-xs text-slate-500">{{ $guard->phone ?? 'No phone' }}</dd>
                            </div>
                            <div class="min-w-0">
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800">Shift</dt>
                                <dd class="mt-1 truncate">{{ $guard->shift ?? 'Unassigned' }}</dd>
                            </div>
                        </dl>

                        <div class="mt-3 grid grid-cols-2 gap-2">
                            <button type="button" data-edit-guard-id="{{ $guard->id }}" x-on:click="openEditGuardModal($event.currentTarget.dataset.editGuardId)" class="inline-flex h-9 items-center justify-center whitespace-nowrap rounded-md border border-blue-200 px-2 text-xs font-semibold text-blue-700 hover:bg-blue-50">Edit</button>
                            <button type="button" data-delete-action="{{ route('guards.destroy', $guard) }}" data-delete-name="{{ $guard->name }}" x-on:click="openDeleteGuardModal($event.currentTarget.dataset.deleteAction, $event.currentTarget.dataset.deleteName)" class="inline-flex h-9 w-full items-center justify-center whitespace-nowrap rounded-md border border-red-200 px-2 text-xs font-semibold text-red-700 hover:bg-red-50">Delete</button>
                        </div>
                    </article>
                @empty
                    <div class="col-span-2 rounded-md border border-blue-100 bg-white px-5 py-8 text-center text-slate-500 shadow-sm">No guards registered.</div>
                @endforelse
            </div>

            <div
                class="hidden rounded-md border border-blue-100 bg-white shadow-sm lg:block"
                :class="sidePanelOpen() ? 'lg:h-[42rem] lg:max-h-[calc(100vh-12rem)] lg:overflow-auto' : 'overflow-hidden'"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-extrabold uppercase text-blue-800">
                            <tr>
                                <th class="px-5 py-3">Employee</th>
                                <th class="px-5 py-3">Contact</th>
                                <th class="px-5 py-3">Account</th>
                                <th class="px-5 py-3">RFID UID</th>
                                @if ($faceVerificationEnabled)
                                    <th class="px-5 py-3">Face Registration</th>
                                @endif
                                <th class="px-5 py-3">Shift</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            @forelse ($guards as $guard)
                                @php
                                    $hasLiveFaceRegistration = \App\Support\FaceVerification::hasCompleteRegistration($guard->faceDescriptors);
                                @endphp
                                <tr>
                                    <td class="px-5 py-4">
                                        <button
                                            type="button"
                                            data-records-url="{{ route('guards.records', $guard) }}"
                                            x-on:click="openGuardRecord($event.currentTarget.dataset.recordsUrl)"
                                            class="text-left font-medium text-slate-900 transition hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                                        >
                                            {{ $guard->name }}
                                        </button>
                                        <div class="text-xs text-slate-500">{{ $guard->employee_no }}</div>
                                    </td>
                                    <td class="px-5 py-4 text-slate-600">
                                        <div>{{ $guard->email ?? 'No email' }}</div>
                                        <div class="text-xs">{{ $guard->phone ?? 'No phone' }}</div>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="font-mono text-sm font-semibold text-blue-800">{{ $guard->user?->username ?? 'No account' }}</div>
                                        <div class="text-xs text-slate-500">{{ $guard->user?->role ? ucfirst($guard->user->role) : '' }}</div>
                                    </td>
                                    <td class="px-5 py-4 font-mono text-slate-700">{{ $guard->rfid_uid }}</td>
                                    @if ($faceVerificationEnabled)
                                        <td class="px-5 py-4 text-slate-600">{{ $hasLiveFaceRegistration ? 'Registered' : 'Not registered' }}</td>
                                    @endif
                                    <td class="px-5 py-4 text-slate-600">{{ $guard->shift ?? 'Unassigned' }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $guard->status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-50 text-slate-600 ring-slate-200' }}">
                                            {{ ucfirst($guard->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            <button type="button" data-edit-guard-id="{{ $guard->id }}" x-on:click="openEditGuardModal($event.currentTarget.dataset.editGuardId)" class="rounded-md border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50">Edit</button>
                                            <button type="button" data-delete-action="{{ route('guards.destroy', $guard) }}" data-delete-name="{{ $guard->name }}" x-on:click="openDeleteGuardModal($event.currentTarget.dataset.deleteAction, $event.currentTarget.dataset.deleteName)" class="rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50">Delete</button>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="{{ $faceVerificationEnabled ? 8 : 7 }}" class="px-5 py-8 text-center text-slate-500">No guards registered.</td>
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
                x-on:click.self="closeCreateGuardModal()"
                x-on:keydown.escape.window="closeCreateGuardModal()"
                class="fixed inset-0 z-[80] flex items-stretch justify-end overflow-hidden bg-slate-950/40 p-0 lg:static lg:z-auto lg:block lg:bg-transparent lg:p-0"
            >
                <section
                    x-show="createModalOpen"
                    role="dialog"
                    x-bind:aria-modal="isCompactPanelViewport() ? 'true' : 'false'"
                    aria-labelledby="create-guard-title"
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="translate-x-full"
                    x-transition:enter-end="translate-x-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="translate-x-0"
                    x-transition:leave-end="translate-x-full"
                    class="ml-auto flex h-full w-full max-w-2xl flex-col overflow-hidden bg-white shadow-xl sm:rounded-l-lg lg:ml-0 lg:h-[42rem] lg:max-h-[calc(100vh-12rem)] lg:max-w-none lg:rounded-md lg:border lg:border-blue-100 lg:shadow-sm"
                >
                    <header class="flex items-start justify-between gap-4 border-b border-blue-100 px-5 py-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Guard Profile</p>
                            <h3 id="create-guard-title" class="mt-1 text-lg font-semibold text-blue-950">New Guard</h3>
                        </div>
                        <button
                            type="button"
                            x-on:click="closeCreateGuardModal()"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-blue-100 text-slate-500 transition hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            aria-label="Close new guard form"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.22 5.22a.75.75 0 0 1 1.06 0L10 8.94l3.72-3.72a.75.75 0 1 1 1.06 1.06L11.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06L10 11.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06L8.94 10 5.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </header>

                    <form method="POST" action="{{ route('guards.store') }}" class="flex min-h-0 flex-1 flex-col">
                        @csrf

                        <div class="mobile-scroll-area flex-1 overflow-y-auto px-5 py-5">
                            @include('system.guards.partials.form-fields', [
                                'guard' => $newGuard,
                                'faceVerificationEnabled' => $faceVerificationEnabled,
                                'passwordRequired' => true,
                                'formContext' => 'create',
                                'fieldPrefix' => 'create',
                            ])
                        </div>

                        <footer class="flex flex-col-reverse gap-2 border-t border-blue-100 px-5 py-4 sm:flex-row sm:justify-end">
                            <button type="button" x-on:click="closeCreateGuardModal()" class="inline-flex h-10 items-center justify-center rounded-md border border-blue-200 bg-white px-4 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Cancel
                            </button>
                            <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                Create Guard Account
                            </button>
                        </footer>
                    </form>
                </section>
            </div>

            @foreach ($guards as $guard)
                @php
                    $editGuardPasswordRequired = ! $guard->user_id;
                    $editGuardFormContext = 'edit-'.$guard->id;
                @endphp

                <div
                    x-show="editModalOpen && editGuardId === '{{ $guard->id }}'"
                    x-cloak
                    x-transition.opacity.duration.150ms
                    x-on:click.self="closeEditGuardModal()"
                    x-on:keydown.escape.window="closeEditGuardModal()"
                    class="fixed inset-0 z-[80] flex items-stretch justify-end overflow-hidden bg-slate-950/40 p-0 lg:static lg:z-auto lg:block lg:bg-transparent lg:p-0"
                >
                    <section
                        x-show="editModalOpen && editGuardId === '{{ $guard->id }}'"
                        role="dialog"
                        x-bind:aria-modal="isCompactPanelViewport() ? 'true' : 'false'"
                        aria-labelledby="edit-guard-title-{{ $guard->id }}"
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="translate-x-full"
                        x-transition:enter-end="translate-x-0"
                        x-transition:leave="transition ease-in duration-150"
                        x-transition:leave-start="translate-x-0"
                        x-transition:leave-end="translate-x-full"
                        class="ml-auto flex h-full w-full max-w-2xl flex-col overflow-hidden bg-white shadow-xl sm:rounded-l-lg lg:ml-0 lg:h-[42rem] lg:max-h-[calc(100vh-12rem)] lg:max-w-none lg:rounded-md lg:border lg:border-blue-100 lg:shadow-sm"
                    >
                        <header class="flex items-start justify-between gap-4 border-b border-blue-100 px-5 py-4">
                            <div class="min-w-0">
                                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Guard Profile</p>
                                <h3 id="edit-guard-title-{{ $guard->id }}" class="mt-1 truncate text-lg font-semibold text-blue-950">Edit Guard</h3>
                                <p class="mt-1 truncate text-sm text-slate-500">{{ $guard->name }} - {{ $guard->employee_no }}</p>
                            </div>
                            <button
                                type="button"
                                x-on:click="closeEditGuardModal()"
                                class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-blue-100 text-slate-500 transition hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                                aria-label="Close edit guard form"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                    <path fill-rule="evenodd" d="M5.22 5.22a.75.75 0 0 1 1.06 0L10 8.94l3.72-3.72a.75.75 0 1 1 1.06 1.06L11.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06L10 11.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06L8.94 10 5.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                                </svg>
                            </button>
                        </header>

                        <form method="POST" action="{{ route('guards.update', $guard) }}" class="flex min-h-0 flex-1 flex-col">
                            @csrf
                            @method('PUT')

                            <div class="mobile-scroll-area flex-1 overflow-y-auto px-5 py-5">
                                @include('system.guards.partials.form-fields', [
                                    'guard' => $guard,
                                    'faceVerificationEnabled' => $faceVerificationEnabled,
                                    'passwordRequired' => $editGuardPasswordRequired,
                                    'formContext' => $editGuardFormContext,
                                    'fieldPrefix' => 'edit_'.$guard->id,
                                ])
                            </div>

                            <footer class="flex flex-col-reverse gap-2 border-t border-blue-100 px-5 py-4 sm:flex-row sm:justify-end">
                                <button type="button" x-on:click="closeEditGuardModal()" class="inline-flex h-10 items-center justify-center rounded-md border border-blue-200 bg-white px-4 text-sm font-semibold text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
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
                x-on:click.self="closeDeleteGuardModal()"
                x-on:keydown.escape.window="closeDeleteGuardModal()"
                class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/60 p-4"
            >
                <section
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="delete-guard-title"
                    class="w-full max-w-md overflow-hidden rounded-lg bg-white shadow-2xl"
                >
                    <div class="border-b border-red-100 px-5 py-4">
                        <h3 id="delete-guard-title" class="text-lg font-semibold text-red-700">Delete Guard</h3>
                        <p class="mt-1 text-sm text-slate-500">This will remove the guard profile and linked guard account.</p>
                    </div>

                    <div class="px-5 py-5">
                        <p class="text-sm text-slate-700">
                            Are you sure you want to delete
                            <span class="font-semibold text-slate-950" x-text="deleteGuardName"></span>?
                        </p>
                    </div>

                    <form method="POST" x-ref="deleteGuardForm" x-bind:action="deleteGuardAction" class="flex flex-col-reverse gap-2 border-t border-red-100 px-5 py-4 sm:flex-row sm:justify-end">
                        @csrf
                        @method('DELETE')
                        <button type="button" x-ref="deleteGuardCancelButton" x-on:click="closeDeleteGuardModal()" class="inline-flex h-10 items-center justify-center rounded-md border border-slate-200 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Cancel
                        </button>
                        <button type="submit" class="inline-flex h-10 items-center justify-center rounded-md bg-red-600 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2">
                            Delete Guard
                        </button>
                    </form>
                </section>
            </div>

            <div
                x-show="recordModalOpen"
                x-cloak
                x-transition.opacity.duration.200ms
                x-on:click.self="closeGuardRecord()"
                x-on:keydown.escape.window="closeGuardRecord()"
                class="fixed inset-0 z-[80] flex items-stretch justify-center overflow-y-auto bg-slate-950/60 p-0 sm:items-center sm:px-4 sm:py-6"
            >
                <section
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="guard-record-title"
                    class="flex max-h-screen w-full flex-col overflow-hidden bg-white shadow-xl sm:max-h-[90vh] sm:max-w-4xl sm:rounded-lg"
                >
                    <header class="flex items-start justify-between gap-4 border-b border-blue-100 px-5 py-4">
                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Guard Records</p>
                            <h3 id="guard-record-title" class="mt-1 truncate text-lg font-semibold text-blue-950" x-text="selectedGuard?.name || 'Guard records'"></h3>
                            <p class="mt-1 text-sm text-slate-500" x-show="selectedGuard" x-text="guardRecordSubtitle()"></p>
                        </div>
                        <button
                            type="button"
                            x-ref="recordCloseButton"
                            x-on:click="closeGuardRecord()"
                            class="inline-flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-blue-100 text-slate-500 transition hover:bg-blue-50 hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            aria-label="Close guard records"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                                <path fill-rule="evenodd" d="M5.22 5.22a.75.75 0 0 1 1.06 0L10 8.94l3.72-3.72a.75.75 0 1 1 1.06 1.06L11.06 10l3.72 3.72a.75.75 0 1 1-1.06 1.06L10 11.06l-3.72 3.72a.75.75 0 0 1-1.06-1.06L8.94 10 5.22 6.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>
                    </header>

                    <div class="flex-1 overflow-y-auto px-5 py-5">
                        <div x-show="recordLoading" class="rounded-md border border-blue-100 bg-blue-50 px-4 py-6 text-center text-sm font-medium text-blue-800">
                            Loading guard records...
                        </div>

                        <div x-show="recordError && ! recordLoading" class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700" x-text="recordError"></div>

                        <div x-show="! recordLoading && ! recordError && selectedGuard" class="space-y-6">
                            <div class="grid gap-4 lg:grid-cols-[1.2fr_1fr]">
                                <dl class="grid gap-3 rounded-md border border-blue-100 bg-blue-50/50 p-4 text-sm sm:grid-cols-2">
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-blue-800">Contact</dt>
                                        <dd class="mt-1 text-slate-700" x-text="selectedGuard?.email || 'No email'"></dd>
                                        <dd class="text-xs text-slate-500" x-text="selectedGuard?.phone || 'No phone'"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-blue-800">Account</dt>
                                        <dd class="mt-1 font-mono font-semibold text-blue-900" x-text="selectedGuard?.username || 'No account'"></dd>
                                        <dd class="text-xs text-slate-500" x-text="selectedGuard?.role ? selectedGuard.role.charAt(0).toUpperCase() + selectedGuard.role.slice(1) : ''"></dd>
                                    </div>
                                    @if ($faceVerificationEnabled)
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-blue-800">Face Registration</dt>
                                        <dd class="mt-1 text-slate-700" x-text="selectedGuard?.face_registration || 'Not registered'"></dd>
                                    </div>
                                    @endif
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-blue-800">Shift / Status</dt>
                                        <dd class="mt-1 text-slate-700" x-text="selectedGuard?.shift || 'Unassigned'"></dd>
                                        <dd class="text-xs text-slate-500" x-text="selectedGuard?.status_label || 'Unknown'"></dd>
                                    </div>
                                </dl>

                                <div class="grid grid-cols-2 gap-3 text-sm">
                                    <div class="rounded-md border border-blue-100 p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-blue-800">Total Scans</p>
                                        <p class="mt-2 text-2xl font-semibold text-blue-950" x-text="recordStats.total_scans ?? 0"></p>
                                    </div>
                                    <div class="rounded-md border border-emerald-100 p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Completed</p>
                                        <p class="mt-2 text-2xl font-semibold text-emerald-700" x-text="recordStats.completed_patrols ?? 0"></p>
                                    </div>
                                    <div class="rounded-md border border-amber-100 p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-amber-700">Suspicious</p>
                                        <p class="mt-2 text-2xl font-semibold text-amber-700" x-text="recordStats.suspicious_patrols ?? 0"></p>
                                    </div>
                                    <div class="rounded-md border border-red-100 p-3">
                                        <p class="text-xs font-semibold uppercase tracking-wide text-red-700">Incidents</p>
                                        <p class="mt-2 text-2xl font-semibold text-red-700" x-text="recordStats.incident_reports ?? 0"></p>
                                    </div>
                                </div>
                            </div>

                            <section>
                                <div class="mb-3 flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-semibold uppercase tracking-wide text-blue-800">Recent Patrol Scans</h4>
                                    @if ($faceVerificationEnabled)
                                    <span class="text-xs text-slate-500" x-text="`${recordStats.failed_face_attempts ?? 0} failed face attempt${(recordStats.failed_face_attempts ?? 0) === 1 ? '' : 's'}`"></span>
                                    @endif
                                </div>
                                <template x-if="recordPatrols.length === 0">
                                    <p class="rounded-md border border-blue-100 px-4 py-5 text-center text-sm text-slate-500">No patrol records yet.</p>
                                </template>
                                <div x-show="recordPatrols.length > 0" class="overflow-x-auto rounded-md border border-blue-100">
                                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                                        <thead class="bg-blue-50/70 text-left text-xs font-extrabold uppercase text-blue-800">
                                            <tr>
                                                <th class="px-4 py-3">Date / Time</th>
                                                <th class="px-4 py-3">Checkpoint</th>
                                                <th class="px-4 py-3">RFID</th>
                                                @if ($faceVerificationEnabled)
                                                <th class="px-4 py-3">Face</th>
                                                @endif
                                                <th class="px-4 py-3">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y divide-blue-50">
                                            <template x-for="patrol in recordPatrols" :key="patrol.id">
                                                <tr>
                                                    <td class="px-4 py-3 text-slate-600" x-text="patrol.scanned_at || 'No date'"></td>
                                                    <td class="px-4 py-3">
                                                        <div class="font-medium text-slate-900" x-text="patrol.checkpoint"></div>
                                                        <div class="text-xs text-slate-500" x-text="patrol.checkpoint_code || ''"></div>
                                                    </td>
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1" :class="badgeClass(patrol.rfid_status)" x-text="patrol.rfid_status_label"></span>
                                                    </td>
                                                    @if ($faceVerificationEnabled)
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1" :class="badgeClass(patrol.facial_status)" x-text="patrol.facial_status_label"></span>
                                                    </td>
                                                    @endif
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1" :class="badgeClass(patrol.status)" x-text="patrol.status_label"></span>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </section>

                            <section>
                                <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-blue-800">Recent Incident Reports</h4>
                                <template x-if="recordIncidents.length === 0">
                                    <p class="rounded-md border border-blue-100 px-4 py-5 text-center text-sm text-slate-500">No incident reports recorded.</p>
                                </template>
                                <div x-show="recordIncidents.length > 0" class="grid gap-3">
                                    <template x-for="incident in recordIncidents" :key="incident.id">
                                        <article class="rounded-md border border-blue-100 p-4">
                                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                                <div>
                                                    <h5 class="font-semibold text-slate-900" x-text="incident.title || 'Incident report'"></h5>
                                                    <p class="mt-1 text-sm text-slate-500" x-text="`${incident.checkpoint} - ${incident.reported_at || 'No date'}`"></p>
                                                </div>
                                                <span class="inline-flex w-fit whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1" :class="badgeClass(incident.status)" x-text="incident.status_label"></span>
                                            </div>
                                            <p class="mt-2 text-sm text-slate-600" x-text="[incident.type, incident.priority_label].filter(Boolean).join(' / ')"></p>
                                        </article>
                                    </template>
                                </div>
                            </section>

                            @if ($faceVerificationEnabled)
                            <section>
                                <h4 class="mb-3 text-sm font-semibold uppercase tracking-wide text-blue-800">Recent Face Verification Attempts</h4>
                                <template x-if="recordFaceAttempts.length === 0">
                                    <p class="rounded-md border border-blue-100 px-4 py-5 text-center text-sm text-slate-500">No face verification attempts recorded.</p>
                                </template>
                                <div x-show="recordFaceAttempts.length > 0" class="grid gap-2">
                                    <template x-for="attempt in recordFaceAttempts" :key="attempt.id">
                                        <div class="flex flex-col gap-2 rounded-md border border-blue-100 px-4 py-3 text-sm sm:flex-row sm:items-center sm:justify-between">
                                            <div>
                                                <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1" :class="badgeClass(attempt.status)" x-text="attempt.status_label"></span>
                                                <span class="ml-2 text-slate-500" x-text="attempt.verified_at || attempt.created_at || 'No date'"></span>
                                                <p class="mt-1 text-xs text-slate-500" x-show="attempt.liveness_label" x-text="`Liveness: ${attempt.liveness_label}`"></p>
                                            </div>
                                            <p class="text-xs text-slate-500" x-show="attempt.match_distance" x-text="`Distance ${attempt.match_distance} / threshold ${attempt.match_threshold}`"></p>
                                        </div>
                                    </template>
                                </div>
                            </section>
                            @endif
                        </div>
                    </div>
                </section>
            </div>

            {{ $guards->links() }}
        </div>
    </div>
</x-app-layout>
