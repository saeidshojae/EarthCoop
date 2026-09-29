<?php

namespace App\Http\Requests\API\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DevicePushRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'provider' => ['required', 'string', Rule::in(['fcm', 'apns', 'hms'])],
            'token' => ['required', 'string', 'max:4096'],
        ];
    }
}
