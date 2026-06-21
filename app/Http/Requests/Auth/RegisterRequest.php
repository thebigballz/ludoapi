<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'min:2', 'max:80'],
            'phone'    => ['required', 'string', 'regex:/^254[0-9]{9}$/', 'unique:users,phone'],
            'email'    => ['nullable', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.regex' => 'Phone must be in format 2547XXXXXXXX or 2541XXXXXXXX.',
        ];
    }
}