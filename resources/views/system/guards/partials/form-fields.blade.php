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
        <x-floating-input
            id="{{ $fieldPrefix }}_employee_no"
            name="employee_no"
            label="Employee No."
            :value="$valueFor('employee_no', $guard->employee_no)"
            required
            @if ($formContext === 'create') x-ref="createGuardFirstField" @endif
            @if ($guard->exists) data-edit-guard-first-field="{{ $guard->id }}" @endif
        />
        <x-input-error :messages="$errorFor('employee_no')" class="mt-2" />
    </div>

    <div>
        <x-floating-input id="{{ $fieldPrefix }}_name" name="name" label="Full Name" :value="$valueFor('name', $guard->name)" required />
        <x-input-error :messages="$errorFor('name')" class="mt-2" />
    </div>

    <div>
        <x-floating-input id="{{ $fieldPrefix }}_email" name="email" type="email" label="Email" :value="$valueFor('email', $guard->email)" />
        <x-input-error :messages="$errorFor('email')" class="mt-2" />
    </div>

    <div>
        <x-floating-input id="{{ $fieldPrefix }}_phone" name="phone" label="Phone" :value="$valueFor('phone', $guard->phone)" />
        <x-input-error :messages="$errorFor('phone')" class="mt-2" />
    </div>

    <div>
        <x-floating-input id="{{ $fieldPrefix }}_rfid_uid" name="rfid_uid" label="RFID UID" :value="$valueFor('rfid_uid', $guard->rfid_uid)" class="font-mono" required />
        <x-input-error :messages="$errorFor('rfid_uid')" class="mt-2" />
    </div>

    @if ($faceVerificationEnabled)
        <div>
            <x-floating-input id="{{ $fieldPrefix }}_face_reference" name="face_reference" label="Face Reference" :value="$valueFor('face_reference', $guard->face_reference)" />
            <x-input-error :messages="$errorFor('face_reference')" class="mt-2" />
        </div>
    @endif

    <div>
        <x-floating-input id="{{ $fieldPrefix }}_shift" name="shift" label="Shift" :value="$valueFor('shift', $guard->shift)" />
        <x-input-error :messages="$errorFor('shift')" class="mt-2" />
    </div>

    <div>
        <x-floating-select id="{{ $fieldPrefix }}_status" name="status" label="Status">
            <option value="active" @selected($valueFor('status', $guard->status) === 'active')>Active</option>
            <option value="inactive" @selected($valueFor('status', $guard->status) === 'inactive')>Inactive</option>
        </x-floating-select>
        <x-input-error :messages="$errorFor('status')" class="mt-2" />
    </div>

    <div class="border-t border-blue-100 pt-5 md:col-span-2">
        <h4 class="text-base font-semibold text-blue-950">Login Account</h4>
    </div>

    <div>
        <x-floating-input
            id="{{ $fieldPrefix }}_username"
            name="username"
            type="text"
            label="Username or Email"
            :value="$valueFor('username', $guard->user?->username)"
            class="font-mono"
            required
            autocomplete="username"
            inputmode="email"
        />
        <x-input-error :messages="$errorFor('username')" class="mt-2" />
    </div>

    <div>
        <x-floating-password
            id="{{ $fieldPrefix }}_password"
            name="password"
            label="{{ $passwordRequired ? 'Password' : 'New Password' }}"
            :required="$passwordRequired"
            autocomplete="new-password"
        />
        <x-input-error :messages="$errorFor('password')" class="mt-2" />
    </div>

    <div>
        <x-floating-password
            id="{{ $fieldPrefix }}_password_confirmation"
            name="password_confirmation"
            label="Confirm Password"
            toggle-label="Show confirm password"
            :required="$passwordRequired"
            autocomplete="new-password"
        />
    </div>

    <div class="md:col-span-2">
        <x-floating-textarea id="{{ $fieldPrefix }}_notes" name="notes" label="Notes" rows="3" :value="$valueFor('notes', $guard->notes)" />
        <x-input-error :messages="$errorFor('notes')" class="mt-2" />
    </div>
</div>
