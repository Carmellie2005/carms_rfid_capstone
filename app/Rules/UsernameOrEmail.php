<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class UsernameOrEmail implements ValidationRule
{
    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $value = trim((string) $value);

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (preg_match('/^[A-Za-z0-9._-]+$/', $value) === 1) {
            return;
        }

        $fail('The :attribute must be a valid email address or contain only letters, numbers, dots, underscores, and dashes.');
    }
}
