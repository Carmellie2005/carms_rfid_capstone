@php
    $faceVerificationEnabled ??= \App\Support\FaceVerification::enabled();
    $passwordRequired ??= ! $guard->exists || ! $guard->user_id;
    $formContext ??= $guard->exists ? 'edit-'.$guard->id : 'create';
    $fieldPrefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $fieldPrefix ?? $formContext);
    $oldFormContext = old('_guard_form');
    $contextMatchesOldInput = $oldFormContext === null ? false : $oldFormContext === $formContext;
    $valueFor = fn ($field, $default = null) => $contextMatchesOldInput ? old($field, $default) : $default;
    $errorFor = fn ($field) => $contextMatchesOldInput ? $errors->get($field) : [];
@endphp

<input type="hidden" name="_guard_form" value="{{ $formContext }}">

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <label for="{{ $fieldPrefix }}_employee_no" class="block text-sm font-medium text-slate-700">Employee No.</label>
        <input
            id="{{ $fieldPrefix }}_employee_no"
            @if ($formContext === 'create') x-ref="createGuardFirstField" @endif
            @if ($guard->exists) data-edit-guard-first-field="{{ $guard->id }}" @endif
            name="employee_no"
            value="{{ $valueFor('employee_no', $guard->employee_no) }}"
            class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
            required
        >
        <x-input-error :messages="$errorFor('employee_no')" class="mt-2" />
    </div>

    <div>
        <label for="{{ $fieldPrefix }}_name" class="block text-sm font-medium text-slate-700">Full Name</label>
        <input id="{{ $fieldPrefix }}_name" name="name" value="{{ $valueFor('name', $guard->name) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
        <x-input-error :messages="$errorFor('name')" class="mt-2" />
    </div>

    <div>
        <label for="{{ $fieldPrefix }}_email" class="block text-sm font-medium text-slate-700">Email</label>
        <input id="{{ $fieldPrefix }}_email" name="email" type="email" value="{{ $valueFor('email', $guard->email) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <x-input-error :messages="$errorFor('email')" class="mt-2" />
    </div>

    <div>
        <label for="{{ $fieldPrefix }}_phone" class="block text-sm font-medium text-slate-700">Phone</label>
        <input id="{{ $fieldPrefix }}_phone" name="phone" value="{{ $valueFor('phone', $guard->phone) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <x-input-error :messages="$errorFor('phone')" class="mt-2" />
    </div>

    <div>
        <label for="{{ $fieldPrefix }}_rfid_uid" class="block text-sm font-medium text-slate-700">RFID UID</label>
        <input id="{{ $fieldPrefix }}_rfid_uid" name="rfid_uid" value="{{ $valueFor('rfid_uid', $guard->rfid_uid) }}" class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
        <x-input-error :messages="$errorFor('rfid_uid')" class="mt-2" />
    </div>

    @if ($faceVerificationEnabled)
        <div>
            <label for="{{ $fieldPrefix }}_face_reference" class="block text-sm font-medium text-slate-700">Face Reference</label>
            <input id="{{ $fieldPrefix }}_face_reference" name="face_reference" value="{{ $valueFor('face_reference', $guard->face_reference) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <x-input-error :messages="$errorFor('face_reference')" class="mt-2" />
        </div>
    @endif

    <div>
        <label for="{{ $fieldPrefix }}_shift" class="block text-sm font-medium text-slate-700">Shift</label>
        <input id="{{ $fieldPrefix }}_shift" name="shift" value="{{ $valueFor('shift', $guard->shift) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <x-input-error :messages="$errorFor('shift')" class="mt-2" />
    </div>

    <div>
        <label for="{{ $fieldPrefix }}_status" class="block text-sm font-medium text-slate-700">Status</label>
        <select id="{{ $fieldPrefix }}_status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="active" @selected($valueFor('status', $guard->status) === 'active')>Active</option>
            <option value="inactive" @selected($valueFor('status', $guard->status) === 'inactive')>Inactive</option>
        </select>
        <x-input-error :messages="$errorFor('status')" class="mt-2" />
    </div>

    <div class="border-t border-blue-100 pt-5 md:col-span-2">
        <h4 class="text-base font-semibold text-blue-950">Login Account</h4>
    </div>

    <div>
        <label for="{{ $fieldPrefix }}_username" class="block text-sm font-medium text-slate-700">Username or Email</label>
        <input id="{{ $fieldPrefix }}_username" name="username" type="text" value="{{ $valueFor('username', $guard->user?->username) }}" class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500" required autocomplete="username" inputmode="email">
        <x-input-error :messages="$errorFor('username')" class="mt-2" />
    </div>

    <div>
        <label for="{{ $fieldPrefix }}_password" class="block text-sm font-medium text-slate-700">{{ $passwordRequired ? 'Password' : 'New Password' }}</label>
        <div x-data="{ showPassword: false }" class="relative mt-1">
            <input id="{{ $fieldPrefix }}_password" name="password" type="password" :type="showPassword ? 'text' : 'password'" class="block w-full rounded-md border-slate-300 pr-11 shadow-sm focus:border-blue-500 focus:ring-blue-500" @required($passwordRequired) autocomplete="new-password">
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
        <x-input-error :messages="$errorFor('password')" class="mt-2" />
    </div>

    <div>
        <label for="{{ $fieldPrefix }}_password_confirmation" class="block text-sm font-medium text-slate-700">Confirm Password</label>
        <div x-data="{ showPassword: false }" class="relative mt-1">
            <input id="{{ $fieldPrefix }}_password_confirmation" name="password_confirmation" type="password" :type="showPassword ? 'text' : 'password'" class="block w-full rounded-md border-slate-300 pr-11 shadow-sm focus:border-blue-500 focus:ring-blue-500" @required($passwordRequired) autocomplete="new-password">
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
        <label for="{{ $fieldPrefix }}_notes" class="block text-sm font-medium text-slate-700">Notes</label>
        <textarea id="{{ $fieldPrefix }}_notes" name="notes" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ $valueFor('notes', $guard->notes) }}</textarea>
        <x-input-error :messages="$errorFor('notes')" class="mt-2" />
    </div>
</div>
