<?php

namespace App\Http\Requests\Group;

use App\Models\PollOption;
use Illuminate\Foundation\Http\FormRequest;

class VotePollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('vote', $this->route('poll')) ?? false;
    }

    public function rules(): array
    {
        return [
            'option_id' => [
                'required',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! PollOption::whereKey($value)->where('poll_id', $this->route('poll')->id)->exists()) {
                        $fail('The selected option is invalid.');
                    }
                },
            ],
        ];
    }
}
