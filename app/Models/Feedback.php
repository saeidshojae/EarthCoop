<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedbacks';

    protected $fillable = [
        'user_id',
        'type',
        'subject',
        'content',
        'rating',
        'analyzed',
        'ai_analysis',
        'status',
        'priority',
    ];

    protected $casts = [
        'analyzed' => 'boolean',
        'rating' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * رابطه با کاربر
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope برای بازخوردهای تحلیل نشده
     */
    public function scopeUnanalyzed($query)
    {
        return $query->where('analyzed', false);
    }

    /**
     * Scope برای بازخوردهای جدید
     */
    public function scopeNew($query)
    {
        return $query->where('status', 'new');
    }
}
