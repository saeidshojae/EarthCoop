<?php

namespace App\Http\Requests\Group;

use App\Models\Blog;
use App\Models\Comment;
use Illuminate\Foundation\Http\FormRequest;

class StoreCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $blog = Blog::find($this->input('blog_id'));

        return $blog !== null && ($this->user()?->can('view', $blog) ?? false);
    }

    public function rules(): array
    {
        return [
            'blog_id' => ['required', 'integer', 'exists:blogs,id'],
            'parent_id' => [
                'nullable',
                'integer',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value !== null && ! Comment::whereKey($value)->where('blog_id', $this->input('blog_id'))->exists()) {
                        $fail('The selected parent comment is invalid.');
                    }
                },
            ],
            'message' => ['required', 'string', 'max:2000'],
        ];
    }
}
