<?php

namespace App\Http\Requests\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    private const MAX_LOGIN_ATTEMPTS = 5;

    private const LOCKOUT_SECONDS = 30;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $login = Str::lower(trim($this->string('email')->toString()));
        $fields = filter_var($login, FILTER_VALIDATE_EMAIL)
            ? ['email', 'username']
            : ['username'];

        foreach ($fields as $field) {
            if (Auth::attempt([$field => $login, 'password' => $this->string('password')->toString()], false)) {
                RateLimiter::clear($this->throttleKey());

                return;
            }
        }

        RateLimiter::hit($this->throttleKey(), self::LOCKOUT_SECONDS);

        if (RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_LOGIN_ATTEMPTS)) {
            $this->throwLockoutException();
        }

        throw ValidationException::withMessages([
            'email' => trans('auth.failed'),
        ]);
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_LOGIN_ATTEMPTS)) {
            return;
        }

        $this->throwLockoutException();
    }

    /**
     * Throw the validation exception shown while login is temporarily locked.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    private function throwLockoutException(): void
    {
        event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());
        $this->session()->flash('login_lockout_seconds', $seconds);

        throw ValidationException::withMessages([
            'email' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }

}
