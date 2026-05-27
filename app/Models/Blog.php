<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    use HasFactory;
    protected $fillable = ['title', 'content', 'img', 'user_id', 'group_id', 'category_id', 'file_type', 'read_by'];

    protected $casts = [
        'read_by' => 'array'
    ];

    public function group(){
        return $this->belongsTo(Group::class);
    }

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function category(){
        return $this->belongsTo(Category::class);
    }

    public function reactions()
{
    return $this->hasMany(Reaction::class, 'blog_id');
}
public function comments()
{
    return $this->hasMany(Comment::class);
}

public function likes()
{
    return $this->reactions()->where('type', '1');
}

public function dislikes()
{
    return $this->reactions()->where('type', '0');
}

protected static function booted()
{
    static::created(function ($blog) {
        if ($blog->group) {
            $blog->group->updateLastActivity();
        }
    });
}

/**
 * Mark blog post as read by user
 */
public function markAsRead(int $userId): void
{
    $readBy = $this->read_by ?? [];
    $readBy[$userId] = now()->toIso8601String();
    $this->update(['read_by' => $readBy]);
}

/**
 * Check if blog post is read by user
 */
public function isReadBy(int $userId): bool
{
    $readBy = $this->read_by ?? [];
    return isset($readBy[$userId]);
}

/**
 * Get read count
 */
public function getReadCountAttribute(): int
{
    return count($this->read_by ?? []);
}

}
