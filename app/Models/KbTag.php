<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class KbTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'color',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function articles(): BelongsToMany
    {
        return $this->belongsToMany(KbArticle::class, 'kb_article_tag', 'kb_tag_id', 'kb_article_id')->withTimestamps();
    }
}




