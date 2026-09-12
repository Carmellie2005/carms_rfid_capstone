@props([
    'disabled' => false,
    'id',
    'label',
    'name' => null,
    'required' => false,
])

<div class="floating-field">
    <select
        id="{{ $id }}"
        name="{{ $name ?? $id }}"
        @required($required)
        {{ $disabled ? 'disabled' : '' }}
        {!! $attributes->class(['floating-control', 'floating-select-control']) !!}
    >
        {{ $slot }}
    </select>
    <label for="{{ $id }}" class="floating-label">{{ $label }}</label>
</div>
