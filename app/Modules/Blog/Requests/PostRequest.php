<?php

namespace App\Modules\Blog\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Temporal\Contracts\TemporalService;
use App\Temporal\Context\TemporalContextResolver;

class PostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    protected function prepareForValidation(): void
    {
        if (! $this->filled('published_at')) {
            return;
        }

        try {
            $instant = app(TemporalService::class)->parseDateTime(
                (string) $this->input('published_at'),
                app(TemporalContextResolver::class)->defaultContext(),
            );

            $this->merge(['published_at' => $instant->format('Y-m-d H:i:s')]);
        } catch (\InvalidArgumentException|\ValueError) {
            // Preserve the submitted value so the ordinary validation rule reports it.
        }
    }

    public function rules()
    {
        $postId = $this->route('post') ? $this->route('post')->id : null;
        
        return [
            'title' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:blog_posts,slug,' . $postId,
            'excerpt' => 'nullable|string|max:500',
            'content' => 'required|string',
            'featured_image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
            'category_id' => 'required|exists:blog_categories,id',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:blog_tags,id',
            'status' => 'required|in:draft,published,archived',
            'published_at' => 'nullable|date',
            'is_featured' => 'boolean',
            'allow_comments' => 'boolean',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:500',
            'meta_keywords' => 'nullable|string|max:255',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes()
    {
        return [
            'title' => 'عنوان',
            'slug' => 'نامک',
            'excerpt' => 'خلاصه',
            'content' => 'محتوا',
            'featured_image' => 'تصویر شاخص',
            'category_id' => 'دسته‌بندی',
            'tags' => 'برچسب‌ها',
            'status' => 'وضعیت',
            'published_at' => 'تاریخ انتشار',
            'is_featured' => 'ویژه',
            'allow_comments' => 'امکان نظر',
            'meta_title' => 'عنوان متا',
            'meta_description' => 'توضیحات متا',
            'meta_keywords' => 'کلمات کلیدی',
        ];
    }
}
