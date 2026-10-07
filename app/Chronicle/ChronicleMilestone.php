<?php

namespace App\Chronicle;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class ChronicleMilestone extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'occurred_on',
        'title_translations',
        'description_translations',
        'is_published',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'occurred_on' => 'date:Y-m-d',
        'title_translations' => 'array',
        'description_translations' => 'array',
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function translatedTitle(?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $translations = $this->title_translations ?? [];

        return (string) (
            $translations[$locale]
            ?? $translations['fa']
            ?? $translations['en']
            ?? reset($translations)
            ?? ''
        );
    }

    public function translatedDescription(?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $translations = $this->description_translations ?? [];
        $value = $translations[$locale]
            ?? $translations['fa']
            ?? $translations['en']
            ?? reset($translations)
            ?? null;

        return $value === null || trim((string) $value) === '' ? null : (string) $value;
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }
}
