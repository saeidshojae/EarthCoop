<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;
use App\Models\User;

class KbArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id',
        'author_id',
        'last_editor_id',
        'title',
        'slug',
        'excerpt',
        'content',
        'status',
        'is_featured',
        'view_count',
        'published_at',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $article) {
            if (empty($article->slug)) {
                $article->slug = Str::slug($article->title) . '-' . Str::random(4);
            }
        });

        static::updating(function (self $article) {
            if (!$article->isDirty('slug') && $article->isDirty('title')) {
                $article->slug = Str::slug($article->title) . '-' . Str::random(4);
            }
        });
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class, 'category_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function lastEditor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_editor_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(KbTag::class, 'kb_article_tag', 'kb_article_id', 'kb_tag_id')->withTimestamps();
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}

