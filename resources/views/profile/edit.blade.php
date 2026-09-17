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

            @if ($user->must_change_password)
                <div class="rounded-md border border-amber-200 bg-amber-50 p-4 shadow-sm dark:border-amber-400/35 dark:bg-amber-950/40">
                    <p class="text-[0.68rem] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-200">Temporary Password</p>
                    <h3 class="mt-1 text-base font-semibold text-blue-950 dark:text-white">Change Your Password Before Scanning</h3>
                    <p class="mt-1 text-sm leading-6 text-slate-700 dark:text-slate-200">
                        Your supervisor assigned this login password. Create your own password first so checkpoint scans stay tied to your account securely.
                    </p>
                    <div class="mt-3 grid gap-2 text-xs font-medium text-amber-800 dark:text-amber-100 sm:grid-cols-2">
                        <span class="rounded-md bg-white/80 px-3 py-2 ring-1 ring-amber-100 dark:bg-slate-950/35 dark:ring-amber-400/25">Use at least 8 characters.</span>
                        <span class="rounded-md bg-white/80 px-3 py-2 ring-1 ring-amber-100 dark:bg-slate-950/35 dark:ring-amber-400/25">Mix letters, numbers, or symbols.</span>
                        <span class="rounded-md bg-white/80 px-3 py-2 ring-1 ring-amber-100 dark:bg-slate-950/35 dark:ring-amber-400/25">Avoid your name, birthday, or employee number.</span>
                        <span class="rounded-md bg-white/80 px-3 py-2 ring-1 ring-amber-100 dark:bg-slate-950/35 dark:ring-amber-400/25">Keep it private.</span>
                    </div>
                </div>
            @endif

            <div class="rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-4">
                @include('profile.partials.update-profile-information-form')
            </div>

            <div id="update-password" class="scroll-mt-24 rounded-md border border-blue-100 bg-white p-3 shadow-sm sm:p-4">
                @include('profile.partials.update-password-form')
            </div>
        </div>
    </div>
</x-app-layout>
