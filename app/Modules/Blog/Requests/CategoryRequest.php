<?php

namespace App\Modules\Blog\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
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
    public function rules()
    {
        $categoryId = $this->route('category') ? $this->route('category')->id : null;
        
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|unique:blog_categories,slug,' . $categoryId,
            'description' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'is_active' => 'boolean',
            'order' => 'integer|min:0',
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
            'name' => 'نام',
            'slug' => 'نامک',
            'description' => 'توضیحات',
            'image' => 'تصویر',
            'is_active' => 'فعال',
            'order' => 'ترتیب',
        ];
    }
}
