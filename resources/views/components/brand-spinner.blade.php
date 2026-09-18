@props([
    'description' => null,
    'dots' => false,
])

<div {{ $attributes->merge(['class' => 'flex flex-col items-center justify-center text-center']) }} role="status" aria-live="polite">
    <span class="brand-spinner-icon">
        <img src="{{ asset('images/slsu-rfid-system-logo-ai-v2.png') }}" alt="" class="h-12 w-12 object-contain">
    </span>

    @if ($dots)
        <span class="brand-spinner-dots mt-3" aria-hidden="true">
            <span></span>
            <span></span>
            <span></span>
        </span>
    @endif

    <span class="{{ $dots ? 'mt-3' : 'mt-4' }} block text-sm font-semibold">
        {{ $slot }}
    </span>

    @isset($description)
        <span class="mt-1 block text-xs leading-5 opacity-80">
            {{ $description }}
        </span>
    @endisset
</div>
