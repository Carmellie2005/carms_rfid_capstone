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
        <label for="{{ $fieldPrefix }}_code" class="block text-sm font-medium text-slate-700">Checkpoint Code</label>
        <input
            id="{{ $fieldPrefix }}_code"
            @if ($formContext === 'create') x-ref="createCheckpointFirstField" @endif
            @if ($checkpoint->exists) data-edit-checkpoint-first-field="{{ $checkpoint->id }}" @endif
            name="code"
            value="{{ $valueFor('code', $checkpoint->code) }}"
            class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500"
            required
        >
        <x-input-error :messages="$errorFor('code')" class="mt-2" />
    </div>

    <div>
        <label for="{{ $fieldPrefix }}_name" class="block text-sm font-medium text-slate-700">Checkpoint Name</label>
        <input id="{{ $fieldPrefix }}_name" name="name" value="{{ $valueFor('name', $checkpoint->name) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
        <x-input-error :messages="$errorFor('name')" class="mt-2" />
    </div>

    <div>
        <label for="{{ $fieldPrefix }}_location" class="block text-sm font-medium text-slate-700">Location</label>
        <input id="{{ $fieldPrefix }}_location" name="location" value="{{ $valueFor('location', $checkpoint->location) }}" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500" required>
        <x-input-error :messages="$errorFor('location')" class="mt-2" />
    </div>

    <div>
        <label for="{{ $fieldPrefix }}_device_uid" class="block text-sm font-medium text-slate-700">Device UID</label>
        <input id="{{ $fieldPrefix }}_device_uid" name="device_uid" value="{{ $valueFor('device_uid', $checkpoint->device_uid) }}" class="mt-1 block w-full rounded-md border-slate-300 font-mono shadow-sm focus:border-blue-500 focus:ring-blue-500">
        <x-input-error :messages="$errorFor('device_uid')" class="mt-2" />
    </div>

    <div>
        <label for="{{ $fieldPrefix }}_status" class="block text-sm font-medium text-slate-700">Status</label>
        <select id="{{ $fieldPrefix }}_status" name="status" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">
            <option value="active" @selected($valueFor('status', $checkpoint->status) === 'active')>Active</option>
            <option value="inactive" @selected($valueFor('status', $checkpoint->status) === 'inactive')>Inactive</option>
        </select>
        <x-input-error :messages="$errorFor('status')" class="mt-2" />
    </div>

    <div class="md:col-span-2">
        <label for="{{ $fieldPrefix }}_description" class="block text-sm font-medium text-slate-700">Description</label>
        <textarea id="{{ $fieldPrefix }}_description" name="description" rows="3" class="mt-1 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500">{{ $valueFor('description', $checkpoint->description) }}</textarea>
        <x-input-error :messages="$errorFor('description')" class="mt-2" />
    </div>
</div>
