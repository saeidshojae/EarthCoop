<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reaction extends Model
{
    use HasFactory;
    protected $fillable = ['blog_id','user_id','type', 'react_type', 'comment_id'];

    public function post()
    {
        return $this->belongsTo(Blog::class);
        // یا Blog::class اگر از Blog استفاده می‌کنی
    }

    public function blog()
    {
        return $this->belongsTo(Blog::class);
    }
    public function comment()
    {
        return $this->belongsTo(Comment::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

}
