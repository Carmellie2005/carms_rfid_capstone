@php
    $formContext ??= $checkpoint->exists ? 'edit-'.$checkpoint->id : 'create';
    $fieldPrefix = preg_replace('/[^A-Za-z0-9_-]/', '_', $fieldPrefix ?? $formContext);
    $oldFormContext = old('_checkpoint_form');
    $contextMatchesOldInput = $oldFormContext === null ? false : $oldFormContext === $formContext;
    $valueFor = fn ($field, $default = null) => $contextMatchesOldInput ? old($field, $default) : $default;
    $errorFor = fn ($field) => $contextMatchesOldInput ? $errors->get($field) : [];
@endphp

<input type="hidden" name="_checkpoint_form" value="{{ $formContext }}">

<div class="grid gap-5 md:grid-cols-2">
    <div>
        <x-floating-input
            id="{{ $fieldPrefix }}_code"
            name="code"
            label="Checkpoint Code"
            :value="$valueFor('code', $checkpoint->code)"
            class="font-mono"
            required
            @if ($formContext === 'create') x-ref="createCheckpointFirstField" @endif
            @if ($checkpoint->exists) data-edit-checkpoint-first-field="{{ $checkpoint->id }}" @endif
        />
        <x-input-error :messages="$errorFor('code')" class="mt-2" />
    </div>

    <div>
        <x-floating-input id="{{ $fieldPrefix }}_name" name="name" label="Checkpoint Name" :value="$valueFor('name', $checkpoint->name)" required />
        <x-input-error :messages="$errorFor('name')" class="mt-2" />
    </div>

    <div>
        <x-floating-input id="{{ $fieldPrefix }}_location" name="location" label="Location" :value="$valueFor('location', $checkpoint->location)" required />
        <x-input-error :messages="$errorFor('location')" class="mt-2" />
    </div>

    <div>
        <x-floating-input id="{{ $fieldPrefix }}_device_uid" name="device_uid" label="Device UID" :value="$valueFor('device_uid', $checkpoint->device_uid)" class="font-mono" />
        <x-input-error :messages="$errorFor('device_uid')" class="mt-2" />
    </div>

    <div>
        <x-floating-select id="{{ $fieldPrefix }}_status" name="status" label="Status">
            <option value="active" @selected($valueFor('status', $checkpoint->status) === 'active')>Active</option>
            <option value="inactive" @selected($valueFor('status', $checkpoint->status) === 'inactive')>Inactive</option>
        </x-floating-select>
        <x-input-error :messages="$errorFor('status')" class="mt-2" />
    </div>

    <div class="md:col-span-2">
        <x-floating-textarea id="{{ $fieldPrefix }}_description" name="description" label="Description" rows="3" :value="$valueFor('description', $checkpoint->description)" />
        <x-input-error :messages="$errorFor('description')" class="mt-2" />
    </div>
</div>
