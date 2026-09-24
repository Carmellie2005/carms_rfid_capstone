@php
    $isSupervisor = $user->role === 'admin';
    $isGuard = $user->role === 'guard';
    $guardProfile = $isGuard ? $user->guardProfile : null;
    $roleLabel = $isSupervisor ? 'Supervisor' : ucfirst($user->role ?? 'User');
    $profileCompletionItems = [
        filled($user->name),
        filled($user->username),
        filled($user->email),
        filled($user->phone),
    ];

    if ($isGuard) {
        $profileCompletionItems[] = (bool) $guardProfile;
    }

    $profileCompletionPercent = count($profileCompletionItems) > 0
        ? (int) round((count(array_filter($profileCompletionItems)) / count($profileCompletionItems)) * 100)
        : 0;
@endphp

<section>
    <header>
        <h2 class="text-base font-semibold text-blue-950 dark:text-white">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
            {{ __('Personal account details and verification status.') }}
        </p>

        @if ($isGuard)
            <div class="mt-3 grid gap-3 border-y border-blue-100 py-2 dark:border-slate-700">
                <div>
                    <p class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-300">Profile Completion</p>
                    <p class="mt-0.5 text-lg font-bold text-blue-950 dark:text-white">{{ $profileCompletionPercent }}%</p>
                    <p class="text-xs font-semibold {{ $profileCompletionPercent === 100 ? 'text-emerald-700 dark:text-emerald-200' : 'text-amber-700 dark:text-amber-200' }}">
                        {{ $profileCompletionPercent === 100 ? 'Complete' : 'Needs updates' }}
                    </p>
                </div>
            </div>
        @endif
    </header>

    <form id="send-verification" method="post" action="{{ route('verification.send') }}">
        @csrf
    </form>

    <form
        method="post"
        action="{{ route('profile.update') }}"
        class="mt-4 space-y-4"
    >
        @csrf
        @method('patch')

        <div class="grid gap-3 md:grid-cols-2">
            <div>
                <x-input-label for="name" :value="__('Full Name')" class="sr-only" />
                <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" placeholder="{{ __('Full Name') }}" required autofocus autocomplete="name" />
                <x-input-error class="mt-2" :messages="$errors->get('name')" />
            </div>

            <div>
                <x-input-label for="username" :value="__('Username or Email')" class="sr-only" />
                <x-text-input id="username" name="username" type="text" class="mt-1 block w-full font-mono" :value="old('username', $user->username)" placeholder="{{ __('Username or Email') }}" required autocomplete="username" inputmode="email" />
                <x-input-error class="mt-2" :messages="$errors->get('username')" />
            </div>

            <div>
                <x-input-label for="email" :value="__('Email Address')" class="sr-only" />
                <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" placeholder="{{ __('Email Address') }}" required autocomplete="email" />
                <x-input-error class="mt-2" :messages="$errors->get('email')" />

                @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
                    <div>
                        <p class="mt-2 text-sm text-slate-800">
                            {{ __('Your email address is unverified.') }}

                            <button form="send-verification" class="rounded-md text-sm text-blue-700 underline hover:text-blue-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                                {{ __('Click here to re-send the verification email.') }}
                            </button>
                        </p>

                        @if (session('status') === 'verification-link-sent')
                            <p class="mt-2 text-sm font-medium text-green-600">
                                {{ __('A new verification link has been sent to your email address.') }}
                            </p>
                        @endif
                    </div>
                @endif
            </div>

            <div>
                <x-input-label for="phone" :value="__('Contact Number')" class="sr-only" />
                <x-text-input id="phone" name="phone" type="text" class="mt-1 block w-full" :value="old('phone', $user->phone)" placeholder="{{ __('Contact Number') }}" autocomplete="tel" />
                <x-input-error class="mt-2" :messages="$errors->get('phone')" />
            </div>

            @if ($isGuard || $isSupervisor)
                <div>
                    <x-input-label for="birthday" :value="__('Birthday')" class="sr-only" />
                    <x-text-input id="birthday" name="birthday" type="date" class="mt-1 block w-full" :value="old('birthday', $user->birthday?->toDateString())" max="{{ now()->toDateString() }}" autocomplete="bday" />
                    <x-input-error class="mt-2" :messages="$errors->get('birthday')" />
                </div>
            @endif
        </div>

        @if ($isSupervisor)
            <div class="border-t border-blue-100 pt-4 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-blue-950 dark:text-white">Office Information</h3>
                <dl class="mt-3 grid gap-3 md:grid-cols-2">
                    <div class="border-b border-blue-100 pb-2 dark:border-slate-700">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-300">Position</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">Head / Supervisor</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2 dark:border-slate-700">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-300">Office</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">Security and Safety Services Office</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2 dark:border-slate-700">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-300">Role in System</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $roleLabel }}</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2 dark:border-slate-700">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-300">Birthday</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $user->birthday?->format('M d, Y') ?? 'Not set' }}</dd>
                    </div>
                    <div class="md:col-span-2">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-300">Account Status</dt>
                        <dd class="mt-1 text-sm font-semibold text-emerald-700 dark:text-emerald-200">Active</dd>
                    </div>
                </dl>
            </div>
        @elseif ($isGuard && $guardProfile)
            <div class="border-t border-blue-100 pt-4 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-blue-950 dark:text-white">Guard Information</h3>
                <dl class="mt-3 grid gap-3 md:grid-cols-2">
                    <div class="border-b border-blue-100 pb-2 dark:border-slate-700">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-300">Position</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">Security Guard</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2 dark:border-slate-700">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-300">Office</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">Security and Safety Services Office</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2 dark:border-slate-700">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-300">Employee No.</dt>
                        <dd class="mt-1 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $guardProfile->employee_no }}</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2 dark:border-slate-700">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-300">RFID UID</dt>
                        <dd class="mt-1 font-mono text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $guardProfile->rfid_uid }}</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2 dark:border-slate-700">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-300">Assigned Shift</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-white">{{ $guardProfile->shift ?: 'Unassigned' }}</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2 dark:border-slate-700">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-300">Birthday</dt>
                        <dd class="mt-1 text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $user->birthday?->format('M d, Y') ?? 'Not set' }}</dd>
                    </div>
                    <div class="border-b border-blue-100 pb-2 dark:border-slate-700">
                        <dt class="text-[0.68rem] font-semibold uppercase tracking-wide text-slate-400 dark:text-slate-300">Account Status</dt>
                        <dd class="mt-1 text-sm font-semibold {{ $guardProfile->status === 'active' ? 'text-emerald-700 dark:text-emerald-200' : 'text-slate-600 dark:text-slate-300' }}">{{ ucfirst($guardProfile->status) }}</dd>
                    </div>
                </dl>
            </div>
        @elseif ($isGuard)
            <div class="border-t border-blue-100 pt-4 dark:border-slate-700">
                <h3 class="text-sm font-semibold text-blue-950 dark:text-white">Guard Information</h3>
                <p class="mt-2 text-xs text-amber-700 dark:text-amber-200">This account is not linked to a guard profile yet.</p>
            </div>
        @endif

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save Changes') }}</x-primary-button>

            @if (session('status') === 'profile-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-slate-600"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
