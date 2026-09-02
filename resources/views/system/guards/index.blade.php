<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">{{ __('Guard Management') }}</h2>
                <p class="mt-1 text-sm text-blue-600">Registered guards, RFID cards, and live face registration status</p>
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
        x-data="guardManagementPage({ createModalOpen: @js($errors->any()) })"
        x-on:open-create-guard.window="openCreateGuardModal()"
    >
        <div class="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
            @if (session('status'))
                <div class="rounded-md border border-blue-200 bg-blue-50 px-4 py-3 text-sm font-medium text-blue-800">{{ session('status') }}</div>
            @endif

            <div class="grid grid-cols-2 gap-3 lg:hidden">
                @forelse ($guards as $guard)
                    @php
                        $hasLiveFaceRegistration = $guard->faceDescriptors->contains(fn ($sample) => is_array($sample->descriptor) && count($sample->descriptor) === 128);
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
                            <div>
                                <dt class="text-[0.65rem] font-semibold uppercase text-blue-800">Face Registration</dt>
                                <dd class="mt-1 whitespace-nowrap">{{ $hasLiveFaceRegistration ? 'Registered' : 'Not registered' }}</dd>
                            </div>
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
                            <a href="{{ route('guards.edit', $guard) }}" class="inline-flex h-9 items-center justify-center whitespace-nowrap rounded-md border border-blue-200 px-2 text-xs font-semibold text-blue-700 hover:bg-blue-50">Edit</a>
                            <form method="POST" action="{{ route('guards.destroy', $guard) }}" onsubmit="return confirm('Remove this guard profile?')">
                                @csrf
                                @method('DELETE')
                                <button class="inline-flex h-9 w-full items-center justify-center whitespace-nowrap rounded-md border border-red-200 px-2 text-xs font-semibold text-red-700 hover:bg-red-50" type="submit">Delete</button>
                            </form>
                        </div>
                    </article>
                @empty
                    <div class="col-span-2 rounded-md border border-blue-100 bg-white px-5 py-8 text-center text-slate-500 shadow-sm">No guards registered.</div>
                @endforelse
            </div>

            <div class="hidden overflow-hidden rounded-md border border-blue-100 bg-white shadow-sm lg:block">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                        <thead class="bg-blue-50/70 text-left text-xs font-semibold uppercase text-blue-800">
                            <tr>
                                <th class="px-5 py-3">Employee</th>
                                <th class="px-5 py-3">Contact</th>
                                <th class="px-5 py-3">Account</th>
                                <th class="px-5 py-3">RFID UID</th>
                                <th class="px-5 py-3">Face Registration</th>
                                <th class="px-5 py-3">Shift</th>
                                <th class="px-5 py-3">Status</th>
                                <th class="px-5 py-3 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-blue-50">
                            @forelse ($guards as $guard)
                                @php
                                    $hasLiveFaceRegistration = $guard->faceDescriptors->contains(fn ($sample) => is_array($sample->descriptor) && count($sample->descriptor) === 128);
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
                                    <td class="px-5 py-4 text-slate-600">{{ $hasLiveFaceRegistration ? 'Registered' : 'Not registered' }}</td>
                                    <td class="px-5 py-4 text-slate-600">{{ $guard->shift ?? 'Unassigned' }}</td>
                                    <td class="px-5 py-4">
                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1 {{ $guard->status === 'active' ? 'bg-emerald-50 text-emerald-700 ring-emerald-200' : 'bg-slate-50 text-slate-600 ring-slate-200' }}">
                                            {{ ucfirst($guard->status) }}
                                        </span>
                                    </td>
                                    <td class="px-5 py-4">
                                        <div class="flex justify-end gap-2">
                                            <a href="{{ route('guards.edit', $guard) }}" class="rounded-md border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50">Edit</a>
                                            <form method="POST" action="{{ route('guards.destroy', $guard) }}" onsubmit="return confirm('Remove this guard profile?')">
                                                @csrf
                                                @method('DELETE')
                                                <button class="rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-700 hover:bg-red-50" type="submit">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-5 py-8 text-center text-slate-500">No guards registered.</td>
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
                x-on:click.self="closeCreateGuardModal()"
                x-on:keydown.escape.window="closeCreateGuardModal()"
                class="fixed inset-0 z-[80] flex items-stretch justify-center overflow-y-auto bg-slate-950/60 p-0 sm:items-center sm:px-4 sm:py-6"
            >
                <section
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="create-guard-title"
                    class="flex max-h-screen w-full flex-col overflow-hidden bg-white shadow-xl sm:max-h-[90vh] sm:max-w-3xl sm:rounded-lg"
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
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label for="create_employee_no" class="block text-sm font-medium text-slate-700">Employee No.</label>
                                    <input id="create_employee_no" x-ref="createGuardFirstField" name="employee_no" value="{{ old('employee_no', $newGuard->employee_no) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    <x-input-error :messages="$errors->get('employee_no')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="create_name" class="block text-sm font-medium text-slate-700">Full Name</label>
                                    <input id="create_name" name="name" value="{{ old('name', $newGuard->name) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="create_email" class="block text-sm font-medium text-slate-700">Email</label>
                                    <input id="create_email" name="email" type="email" value="{{ old('email', $newGuard->email) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <x-input-error :messages="$errors->get('email')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="create_phone" class="block text-sm font-medium text-slate-700">Phone</label>
                                    <input id="create_phone" name="phone" value="{{ old('phone', $newGuard->phone) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="create_rfid_uid" class="block text-sm font-medium text-slate-700">RFID UID</label>
                                    <input id="create_rfid_uid" name="rfid_uid" value="{{ old('rfid_uid', $newGuard->rfid_uid) }}" class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                                    <x-input-error :messages="$errors->get('rfid_uid')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="create_face_reference" class="block text-sm font-medium text-slate-700">Face Reference</label>
                                    <input id="create_face_reference" name="face_reference" value="{{ old('face_reference', $newGuard->face_reference) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <x-input-error :messages="$errors->get('face_reference')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="create_shift" class="block text-sm font-medium text-slate-700">Shift</label>
                                    <input id="create_shift" name="shift" value="{{ old('shift', $newGuard->shift) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <x-input-error :messages="$errors->get('shift')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="create_status" class="block text-sm font-medium text-slate-700">Status</label>
                                    <select id="create_status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                        <option value="active" @selected(old('status', $newGuard->status) === 'active')>Active</option>
                                        <option value="inactive" @selected(old('status', $newGuard->status) === 'inactive')>Inactive</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('status')" class="mt-2" />
                                </div>
                                <div class="border-t border-blue-100 pt-4 md:col-span-2">
                                    <h4 class="text-base font-semibold text-blue-950">Login Account</h4>
                                </div>
                                <div>
                                    <label for="create_username" class="block text-sm font-medium text-slate-700">Username</label>
                                    <input id="create_username" name="username" value="{{ old('username', $newGuard->user?->username) }}" class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500" required autocomplete="username">
                                    <x-input-error :messages="$errors->get('username')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="create_password" class="block text-sm font-medium text-slate-700">Password</label>
                                    <div x-data="{ showPassword: false }" class="relative mt-1">
                                        <input id="create_password" name="password" type="password" :type="showPassword ? 'text' : 'password'" class="block w-full rounded-md border-slate-300 pr-11 shadow-sm focus:border-blue-500 focus:ring-blue-500" required autocomplete="new-password">
                                        <button
                                            type="button"
                                            x-on:click="showPassword = ! showPassword"
                                            aria-label="Show password"
                                            title="Show password"
                                            :aria-label="showPassword ? 'Hide password' : 'Show password'"
                                            :title="showPassword ? 'Hide password' : 'Show password'"
                                            class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-md text-slate-400 transition hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
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
                                    <x-input-error :messages="$errors->get('password')" class="mt-2" />
                                </div>
                                <div>
                                    <label for="create_password_confirmation" class="block text-sm font-medium text-slate-700">Confirm Password</label>
                                    <div x-data="{ showPassword: false }" class="relative mt-1">
                                        <input id="create_password_confirmation" name="password_confirmation" type="password" :type="showPassword ? 'text' : 'password'" class="block w-full rounded-md border-slate-300 pr-11 shadow-sm focus:border-blue-500 focus:ring-blue-500" required autocomplete="new-password">
                                        <button
                                            type="button"
                                            x-on:click="showPassword = ! showPassword"
                                            aria-label="Show confirm password"
                                            title="Show confirm password"
                                            :aria-label="showPassword ? 'Hide confirm password' : 'Show confirm password'"
                                            :title="showPassword ? 'Hide confirm password' : 'Show confirm password'"
                                            class="absolute inset-y-0 right-0 inline-flex w-11 items-center justify-center rounded-r-md text-slate-400 transition hover:text-blue-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-500"
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
                                </div>
                                <div class="md:col-span-2">
                                    <label for="create_notes" class="block text-sm font-medium text-slate-700">Notes</label>
                                    <textarea id="create_notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes', $newGuard->notes) }}</textarea>
                                    <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                                </div>
                            </div>
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
                                    <div>
                                        <dt class="text-xs font-semibold uppercase tracking-wide text-blue-800">Face Registration</dt>
                                        <dd class="mt-1 text-slate-700" x-text="selectedGuard?.face_registration || 'Not registered'"></dd>
                                    </div>
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
                                    <span class="text-xs text-slate-500" x-text="`${recordStats.failed_face_attempts ?? 0} failed face attempt${(recordStats.failed_face_attempts ?? 0) === 1 ? '' : 's'}`"></span>
                                </div>
                                <template x-if="recordPatrols.length === 0">
                                    <p class="rounded-md border border-blue-100 px-4 py-5 text-center text-sm text-slate-500">No patrol records yet.</p>
                                </template>
                                <div x-show="recordPatrols.length > 0" class="overflow-x-auto rounded-md border border-blue-100">
                                    <table class="min-w-full divide-y divide-blue-100 text-sm">
                                        <thead class="bg-blue-50/70 text-left text-xs font-semibold uppercase text-blue-800">
                                            <tr>
                                                <th class="px-4 py-3">Date / Time</th>
                                                <th class="px-4 py-3">Checkpoint</th>
                                                <th class="px-4 py-3">RFID</th>
                                                <th class="px-4 py-3">Face</th>
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
                                                    <td class="px-4 py-3">
                                                        <span class="inline-flex whitespace-nowrap rounded-md px-2.5 py-1 text-xs font-semibold ring-1" :class="badgeClass(patrol.facial_status)" x-text="patrol.facial_status_label"></span>
                                                    </td>
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
                                            </div>
                                            <p class="text-xs text-slate-500" x-show="attempt.match_distance" x-text="`Distance ${attempt.match_distance} / threshold ${attempt.match_threshold}`"></p>
                                        </div>
                                    </template>
                                </div>
                            </section>
                        </div>
                    </div>
                </section>
            </div>

            {{ $guards->links() }}
        </div>
    </div>
</x-app-layout>
