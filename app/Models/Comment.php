<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Comment extends Model
{
    use HasFactory;
    protected $fillable = ['user_id', 'blog_id', 'message', 'parent_id'];

    public function user(){
        return $this->belongsTo(User::class);
    }
    public function reactions()
    {
        return $this->hasMany(Reaction::class, 'comment_id');
    }
    public function likes()
{
    return $this->reactions()->where('type', '1');
}

public function dislikes()
{
    return $this->reactions()->where('type', '0');
}

public function parent(){
    return $this->belongsTo(Comment::class);
}
public function blog(){
    return $this->belongsTo(Blog::class);
}
public function childs(){
    return $this->hasMany(Comment::class, 'parent_id');
}

protected static function booted()
{
    static::created(function ($comment) {
        if ($comment->blog && $comment->blog->group) {
            $comment->blog->group->updateLastActivity();
        }
    });
}
}
