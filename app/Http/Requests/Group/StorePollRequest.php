<?php

namespace App\Http\Requests\Group;

use Illuminate\Foundation\Http\FormRequest;

class StorePollRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('participate', $this->route('group')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'options' => collect($this->input('options', []))
                ->map(fn ($option) => is_string($option) ? trim($option) : $option)
                ->all(),
        ]);
    }

    public function rules(): array
    {
        return [
            'question' => ['required', 'string', 'max:255'],
            'options' => ['required', 'array', 'min:2', 'max:10'],
            'options.*' => ['required', 'string', 'max:255', 'distinct:ignore_case'],
            'expires_at' => ['required', 'integer', 'min:1', 'max:365'],
            'type' => ['required', 'integer', 'in:0,1'],
            'skill_id' => ['nullable', 'integer', 'exists:experience_fields,id'],
            'main_type' => ['required', 'integer', 'in:0,1'],
        ];
    }
}
