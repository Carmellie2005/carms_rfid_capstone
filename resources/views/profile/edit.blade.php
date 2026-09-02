<x-app-layout>
    @php
        $isSupervisor = $user->role === 'admin';
        $isGuard = $user->role === 'guard';
    @endphp

    <x-slot name="header">
        <div>
            <h2 class="text-lg font-semibold leading-tight text-blue-950">
                @if ($isSupervisor)
                    {{ __('Supervisor Profile Settings') }}
                @elseif ($isGuard)
                    {{ __('Guard Profile Settings') }}
                @else
                    {{ __('Profile Settings') }}
                @endif
            </h2>
            <p class="mt-1 text-xs text-blue-600">
                @if ($isSupervisor)
                    {{ __('Head / Supervisor, Security and Safety Services Office') }}
                @elseif ($isGuard)
                    {{ __('Security guard account and patrol identity information') }}
                @else
                    {{ __('Manage your account information and security settings') }}
                @endif
            </p>
        </div>
    </x-slot>

    <div class="py-4 sm:py-6">
        <div class="mx-auto max-w-4xl space-y-4 px-4 sm:px-6 lg:px-8">
            <div class="rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-4">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>
    </div>
</x-app-layout>
