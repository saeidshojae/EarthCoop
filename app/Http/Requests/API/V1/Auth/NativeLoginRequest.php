<?php

namespace App\Http\Requests\API\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class NativeLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'platform' => ['required', 'in:ios,android'],
            'app_version' => ['required', 'string', 'max:50'],
            'locale' => ['required', 'in:fa,en,ar'],
            'timezone' => ['nullable', 'timezone'],
            'push_capable' => ['sometimes', 'boolean'],
            'device_id' => ['nullable', 'uuid'],
        ];
    }
}
