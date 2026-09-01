<x-app-layout>
    @php
        $isSupervisor = $user->role === 'admin';
        $isGuard = $user->role === 'guard';
    @endphp

    <x-slot name="header">
        <div>
            <h2 class="text-xl font-semibold leading-tight text-blue-950">
                @if ($isSupervisor)
                    {{ __('Supervisor Profile Settings') }}
                @elseif ($isGuard)
                    {{ __('Guard Profile Settings') }}
                @else
                    {{ __('Profile Settings') }}
                @endif
            </h2>
            <p class="mt-1 text-sm text-blue-600">
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

    <div class="py-5 sm:py-8">
        <div class="mx-auto max-w-5xl space-y-6 px-4 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-6">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-6">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="rounded-lg border border-blue-100 bg-white p-4 shadow-sm sm:p-6">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
