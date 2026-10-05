<section>
    <header>
        <p class="text-[0.68rem] font-semibold uppercase tracking-wide text-blue-700 dark:text-white">
            {{ __('Account Security') }}
        </p>

        <h2 class="mt-1 text-base font-semibold text-blue-950 dark:text-white">
            {{ __('Update Password') }}
        </h2>

        <p class="mt-1 text-sm leading-6 text-slate-600 dark:text-slate-300">
            {{ __('Use a secure password for your account.') }}
        </p>
    </header>

    <form method="post" action="{{ route('password.update') }}" class="mt-5 space-y-4">
        @csrf
        @method('put')

        <div class="grid gap-3 md:grid-cols-3">
        <div>
            <x-input-label for="update_password_current_password" :value="__('Current Password')" class="sr-only" />
            <x-password-input id="update_password_current_password" name="current_password" placeholder="{{ __('Current Password') }}" autocomplete="current-password" show-label="Show current password" hide-label="Hide current password" />
            <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password" :value="__('New Password')" class="sr-only" />
            <x-password-input id="update_password_password" name="password" placeholder="{{ __('New Password') }}" autocomplete="new-password" show-label="Show new password" hide-label="Hide new password" />
            <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-2" />
        </div>

        <div>
            <x-input-label for="update_password_password_confirmation" :value="__('Confirm Password')" class="sr-only" />
            <x-password-input id="update_password_password_confirmation" name="password_confirmation" placeholder="{{ __('Confirm Password') }}" autocomplete="new-password" show-label="Show confirm password" hide-label="Hide confirm password" />
            <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-2" />
        </div>
        </div>

        <div class="flex items-center gap-4">
            <x-primary-button>{{ __('Save') }}</x-primary-button>

            @if (session('status') === 'password-updated')
                <p
                    x-data="{ show: true }"
                    x-show="show"
                    x-transition
                    x-init="setTimeout(() => show = false, 2000)"
                    class="text-sm text-slate-600 dark:text-slate-300"
                >{{ __('Saved.') }}</p>
            @endif
        </div>
    </form>
</section>
