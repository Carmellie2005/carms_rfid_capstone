<?php

namespace App\Http\Requests;

use App\Models\User;
use App\Rules\UsernameOrEmail;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\Rule|array|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255', new UsernameOrEmail, Rule::unique(User::class, 'username')->ignore($this->user()->id), Rule::unique(User::class, 'email')->ignore($this->user()->id)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'face_registration_capture' => ['sometimes', 'nullable', 'string'],
            'face_registration_captures' => ['sometimes', 'nullable', 'array', 'size:5'],
            'face_registration_captures.*' => ['nullable', 'string'],
            'face_liveness_confirmed' => ['sometimes', 'nullable', 'boolean'],
            'face_descriptors' => ['sometimes', 'nullable', 'array', 'size:5'],
            'face_descriptors.*' => ['nullable', 'string'],
        ];
    }
}
