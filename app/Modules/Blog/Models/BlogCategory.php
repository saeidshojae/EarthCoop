<?php

namespace App\Modules\Blog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'image',
        'is_active',
        'order'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get posts for this category
     */
    public function posts()
    {
        return $this->hasMany(Post::class, 'category_id');
    }

    /**
     * Get published posts count
     */
    public function publishedPostsCount()
    {
        return $this->posts()->where('status', 'published')->count();
    }

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope for ordering
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order', 'asc');
    }
}
