<?php

namespace App\Http\Requests;

use App\Models\User;
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
            'username' => ['sometimes', 'nullable', 'string', 'max:50', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique(User::class, 'username')->ignore($this->user()->id)],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user()->id)],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'profile_photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'remove_profile_photo' => ['sometimes', 'boolean'],
            'face_registration_capture' => ['sometimes', 'nullable', 'string'],
            'face_liveness_confirmed' => ['sometimes', 'nullable', 'boolean'],
            'face_descriptors' => ['sometimes', 'nullable', 'array', 'max:1'],
            'face_descriptors.*' => ['nullable', 'string'],
        ];
    }
}
