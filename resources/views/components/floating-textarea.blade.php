@props([
    'disabled' => false,
    'id',
    'label',
    'name' => null,
    'required' => false,
    'rows' => 3,
    'value' => null,
])

<div class="floating-field">
    <textarea
        id="{{ $id }}"
        name="{{ $name ?? $id }}"
        rows="{{ $rows }}"
        placeholder=" "
        @required($required)
        {{ $disabled ? 'disabled' : '' }}
        {!! $attributes->class(['floating-control', 'floating-textarea']) !!}
    >{{ $value }}</textarea>
    <label for="{{ $id }}" class="floating-label">{{ $label }}</label>
</div>
