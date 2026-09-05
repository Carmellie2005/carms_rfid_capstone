<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-xl font-semibold leading-tight text-blue-950">
                    {{ $guard->exists ? 'Edit Guard' : 'New Guard' }}
                </h2>
                <p class="mt-1 text-sm text-blue-600">Guard identity, RFID card, shift, and login account</p>
            </div>
            <a href="{{ route('guards.index') }}" class="inline-flex w-full items-center justify-center rounded-md border border-blue-200 bg-white px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm transition hover:bg-blue-50 sm:w-auto">
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
                class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-6"
            >
                @csrf
                @if ($guard->exists)
                    @method('PUT')
                @endif

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="employee_no" class="block text-sm font-medium text-slate-700">Employee No.</label>
                        <input id="employee_no" name="employee_no" value="{{ old('employee_no', $guard->employee_no) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <x-input-error :messages="$errors->get('employee_no')" class="mt-2" />
                    </div>
                    <div>
                        <label for="name" class="block text-sm font-medium text-slate-700">Full Name</label>
                        <input id="name" name="name" value="{{ old('name', $guard->name) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-700">Email</label>
                        <input id="email" name="email" type="email" value="{{ old('email', $guard->email) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <x-input-error :messages="$errors->get('email')" class="mt-2" />
                    </div>
                    <div>
                        <label for="phone" class="block text-sm font-medium text-slate-700">Phone</label>
                        <input id="phone" name="phone" value="{{ old('phone', $guard->phone) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <x-input-error :messages="$errors->get('phone')" class="mt-2" />
                    </div>
                    <div>
                        <label for="rfid_uid" class="block text-sm font-medium text-slate-700">RFID UID</label>
                        <input id="rfid_uid" name="rfid_uid" value="{{ old('rfid_uid', $guard->rfid_uid) }}" class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
                        <x-input-error :messages="$errors->get('rfid_uid')" class="mt-2" />
                    </div>
                    <div>
                        <label for="face_reference" class="block text-sm font-medium text-slate-700">Face Reference</label>
                        <input id="face_reference" name="face_reference" value="{{ old('face_reference', $guard->face_reference) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <x-input-error :messages="$errors->get('face_reference')" class="mt-2" />
                    </div>
                    <div>
                        <label for="shift" class="block text-sm font-medium text-slate-700">Shift</label>
                        <input id="shift" name="shift" value="{{ old('shift', $guard->shift) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <x-input-error :messages="$errors->get('shift')" class="mt-2" />
                    </div>
                    <div>
                        <label for="status" class="block text-sm font-medium text-slate-700">Status</label>
                        <select id="status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="active" @selected(old('status', $guard->status) === 'active')>Active</option>
                            <option value="inactive" @selected(old('status', $guard->status) === 'inactive')>Inactive</option>
                        </select>
                        <x-input-error :messages="$errors->get('status')" class="mt-2" />
                    </div>
                    <div class="border-t border-blue-100 pt-5 md:col-span-2">
                        <h3 class="text-base font-semibold text-blue-950">Login Account</h3>
                    </div>
                    <div>
                        <label for="username" class="block text-sm font-medium text-slate-700">Username or Email</label>
                        <input id="username" name="username" type="text" value="{{ old('username', $guard->user?->username) }}" class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500" required autocomplete="username" inputmode="email">
                        <x-input-error :messages="$errors->get('username')" class="mt-2" />
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-700">{{ $passwordRequired ? 'Password' : 'New Password' }}</label>
                        <div x-data="{ showPassword: false }" class="relative mt-1">
                            <input id="password" name="password" type="password" :type="showPassword ? 'text' : 'password'" class="block w-full rounded-md border-slate-300 pr-11 shadow-sm focus:border-blue-500 focus:ring-blue-500" @required($passwordRequired) autocomplete="new-password">
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
                        <label for="password_confirmation" class="block text-sm font-medium text-slate-700">Confirm Password</label>
                        <div x-data="{ showPassword: false }" class="relative mt-1">
                            <input id="password_confirmation" name="password_confirmation" type="password" :type="showPassword ? 'text' : 'password'" class="block w-full rounded-md border-slate-300 pr-11 shadow-sm focus:border-blue-500 focus:ring-blue-500" @required($passwordRequired) autocomplete="new-password">
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
                        <label for="notes" class="block text-sm font-medium text-slate-700">Notes</label>
                        <textarea id="notes" name="notes" rows="4" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ old('notes', $guard->notes) }}</textarea>
                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                    </div>
                </div>

                <div class="mt-6 flex justify-stretch sm:justify-end">
                    <x-primary-button class="w-full justify-center sm:w-auto">{{ $guard->exists ? 'Save Changes' : 'Create Guard Account' }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
