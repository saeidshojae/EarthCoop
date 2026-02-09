<?php

namespace App\Modules\Blog\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BlogComment extends Model
{
    use HasFactory;

    protected $fillable = [
        'post_id',
        'user_id',
        'parent_id',
        'content',
        'status'
    ];

    /**
     * Get the post
     */
    public function post()
    {
        return $this->belongsTo(Post::class, 'post_id');
    }

    /**
     * Get the author
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get parent comment
     */
    public function parent()
    {
        return $this->belongsTo(BlogComment::class, 'parent_id');
    }

    /**
     * Get replies
     */
    public function replies()
    {
        return $this->hasMany(BlogComment::class, 'parent_id');
    }

    /**
     * Scope for approved comments
     */
    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    /**
     * Scope for pending comments
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Check if comment is approved
     */
    public function isApproved()
    {
        return $this->status === 'approved';
    }
}
