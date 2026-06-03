<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PrivateChatReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'private_conversation_id',
        'reported_message_id',
        'reporter_id',
        'reported_user_id',
        'reason',
        'description',
        'status',
        'admin_notes',
        'reviewed_by',
        'reviewed_at',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    /**
     * Report reasons
     */
    const REASONS = [
        'spam' => 'اسپم و تبلیغات',
        'harassment' => 'آزار و اذیت',
        'inappropriate_content' => 'محتوای نامناسب',
        'abuse' => 'توهین',
        'other' => 'سایر',
    ];

    /**
     * Report statuses
     */
    const STATUSES = [
        'pending' => 'در انتظار بررسی',
        'reviewed' => 'بررسی شده',
        'resolved' => 'حل شده',
        'dismissed' => 'رد شده',
    ];

    public function conversation()
    {
        return $this->belongsTo(PrivateConversation::class);
    }

    public function message()
    {
        return $this->belongsTo(PrivateMessage::class, 'reported_message_id');
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Scope: pending reports
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }
}