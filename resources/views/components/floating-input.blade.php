@props([
    'disabled' => false,
    'id',
    'label',
    'name' => null,
    'required' => false,
    'type' => 'text',
    'value' => null,
])

<div class="floating-field">
    <input
        id="{{ $id }}"
        name="{{ $name ?? $id }}"
        type="{{ $type }}"
        value="{{ $value }}"
        placeholder=" "
        @required($required)
        {{ $disabled ? 'disabled' : '' }}
        {!! $attributes->class(['floating-control']) !!}
    >
    <label for="{{ $id }}" class="floating-label">{{ $label }}</label>
</div>
