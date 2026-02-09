<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FaqQuestion extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'contact_name',
        'contact_email',
        'title',
        'category',
        'question',
        'answer',
        'status',
        'is_published',
        'answered_at',
        'notified_at',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'answered_at' => 'datetime',
        'notified_at' => 'datetime',
    ];

    public function scopePublished($query)
    {
        return $query->where('is_published', true)->whereNotNull('answer');
    }
}

