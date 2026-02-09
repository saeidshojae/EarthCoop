<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Report extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'reported_by',
        'reported_item_id',
        'reason',
        'description',
        'status',
        'reviewed_by',
        'reviewed_at',
        'admin_note',
        'priority',
        'group_id',
        'report_count',
        'metadata'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'metadata' => 'array'
    ];

    // Relationships
    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    // Polymorphic relationships برای آیتم گزارش شده
    public function reportedItem()
    {
        switch ($this->type) {
            case 'message':
                return $this->belongsTo(Message::class, 'reported_item_id');
            case 'post':
                return $this->belongsTo(Blog::class, 'reported_item_id');
            case 'poll':
                return $this->belongsTo(Poll::class, 'reported_item_id');
            case 'user':
                return $this->belongsTo(User::class, 'reported_item_id');
            default:
                return null;
        }
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeReviewed($query)
    {
        return $query->where('status', 'reviewed');
    }

    public function scopeResolved($query)
    {
        return $query->where('status', 'resolved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeHighPriority($query)
    {
        return $query->whereIn('priority', ['high', 'critical']);
    }

    // Helper methods
    public function getTypeLabelAttribute()
    {
        $types = [
            'message' => 'پیام',
            'post' => 'پست',
            'poll' => 'نظرسنجی',
            'user' => 'کاربر'
        ];
        return $types[$this->type] ?? $this->type;
    }

    public function getStatusLabelAttribute()
    {
        $statuses = [
            'pending' => 'در انتظار بررسی',
            'reviewed' => 'بررسی شده',
            'resolved' => 'حل شده',
            'rejected' => 'رد شده',
            'archived' => 'بایگانی شده'
        ];
        return $statuses[$this->status] ?? $this->status;
    }

    public function getPriorityLabelAttribute()
    {
        $priorities = [
            'low' => 'پایین',
            'medium' => 'متوسط',
            'high' => 'بالا',
            'critical' => 'بحرانی'
        ];
        return $priorities[$this->priority] ?? $this->priority;
    }
}

