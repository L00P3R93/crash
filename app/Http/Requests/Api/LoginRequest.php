<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'msisdn' => ['required', 'string'],
            'pin' => ['required', 'string', 'digits:4'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ];
    }
}
