<?php

namespace App\Modules\Blog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'blog_posts';

    protected $fillable = [
        'title',
        'slug',
        'excerpt',
        'content',
        'featured_image',
        'category_id',
        'user_id',
        'status',
        'published_at',
        'views_count',
        'is_featured',
        'allow_comments',
        'meta_title',
        'meta_description',
        'meta_keywords'
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'is_featured' => 'boolean',
        'allow_comments' => 'boolean',
    ];

    protected $dates = ['deleted_at'];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($post) {
            if (empty($post->slug)) {
                $post->slug = Str::slug($post->title);
            }
        });
    }

    /**
     * Get the category
     */
    public function category()
    {
        return $this->belongsTo(BlogCategory::class, 'category_id');
    }

    /**
     * Get the author
     */
    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get tags
     */
    public function tags()
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tag', 'post_id', 'tag_id')
                    ->withTimestamps();
    }

    /**
     * Get comments
     */
    public function comments()
    {
        return $this->hasMany(BlogComment::class, 'post_id');
    }

    /**
     * Get approved comments
     */
    public function approvedComments()
    {
        return $this->comments()->where('status', 'approved')->whereNull('parent_id');
    }

    /**
     * Scope for published posts
     */
    public function scopePublished($query)
    {
        return $query->where('status', 'published')
                     ->where('published_at', '<=', now());
    }

    /**
     * Scope for featured posts
     */
    public function scopeFeatured($query)
    {
        return $query->where('is_featured', true);
    }

    /**
     * Scope for recent posts
     */
    public function scopeRecent($query, $limit = 5)
    {
        return $query->orderBy('published_at', 'desc')->limit($limit);
    }

    /**
     * Scope for popular posts
     */
    public function scopePopular($query, $limit = 5)
    {
        return $query->orderBy('views_count', 'desc')->limit($limit);
    }

    /**
     * Increment views count
     */
    public function incrementViews()
    {
        $this->increment('views_count');
    }

    /**
     * Get excerpt or generate from content
     */
    public function getExcerptAttribute($value)
    {
        if ($value) {
            return $value;
        }
        
        return Str::limit(strip_tags($this->content), 200);
    }

    /**
     * Get reading time in minutes
     */
    public function getReadingTimeAttribute()
    {
        $words = str_word_count(strip_tags($this->content));
        $minutes = ceil($words / 200); // Average reading speed
        return $minutes;
    }

    /**
     * Check if post is published
     */
    public function isPublished()
    {
        return $this->status === 'published' && $this->published_at <= now();
    }
}
