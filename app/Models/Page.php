<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'template',
        'content',
        'meta_title',
        'meta_description',
        'is_published',
        'show_in_header',
        'title_translations',
        'content_translations',
        'meta_title_translations',
        'meta_description_translations'
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'show_in_header' => 'boolean',
        'title_translations' => 'array',
        'content_translations' => 'array',
        'meta_title_translations' => 'array',
        'meta_description_translations' => 'array'
    ];

    /**
     * Get translated title based on current locale
     */
    public function getTranslatedTitleAttribute()
    {
        $locale = app()->getLocale();
        $translations = $this->title_translations ?? [];
        
        // Return translated title if exists, otherwise return default title
        return $translations[$locale] ?? $this->title;
    }

    /**
     * Get translated content based on current locale
     */
    public function getTranslatedContentAttribute()
    {
        $locale = app()->getLocale();
        $translations = $this->content_translations ?? [];
        
        return $translations[$locale] ?? $this->content;
    }

    /**
     * Get translated meta title based on current locale
     */
    public function getTranslatedMetaTitleAttribute()
    {
        $locale = app()->getLocale();
        $translations = $this->meta_title_translations ?? [];
        
        return $translations[$locale] ?? $this->meta_title;
    }

    /**
     * Get translated meta description based on current locale
     */
    public function getTranslatedMetaDescriptionAttribute()
    {
        $locale = app()->getLocale();
        $translations = $this->meta_description_translations ?? [];
        
        return $translations[$locale] ?? $this->meta_description;
    }
}
