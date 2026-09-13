<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class PlaceBetRequest extends FormRequest
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
            'stake' => ['required', 'numeric', 'min:0.01'],
            'auto_cashout' => ['nullable', 'numeric', 'min:1.01'],
        ];
    }
}
