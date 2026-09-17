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
            @if (session('warning'))
                <div class="rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-medium text-amber-800 dark:border-amber-400/35 dark:bg-amber-950/40 dark:text-amber-100">
                    {{ session('warning') }}
                </div>
            @endif

            <div class="rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-4">
                @include('profile.partials.update-profile-information-form')
            </div>

            @if ($isGuard)
                <div id="update-password" class="scroll-mt-24 rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-4">
                    @include('profile.partials.update-password-form')
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
