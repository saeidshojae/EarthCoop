<?php

namespace App\Modules\Blog\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogTag extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug'
    ];

    /**
     * Get posts with this tag
     */
    public function posts()
    {
        return $this->belongsToMany(Post::class, 'blog_post_tag', 'tag_id', 'post_id')
                    ->withTimestamps();
    }

    /**
     * Get posts count
     */
    public function postsCount()
    {
        return $this->posts()->where('status', 'published')->count();
    }
}
