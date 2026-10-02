<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Models\TicketComment;

class Ticket extends Model
{
    protected $fillable = [
        'user_id',
        'tracking_code',
        'subject',
        'message',
        'status',
        'priority',
        'assignee_id',
        'name',
        'email',
        'phone',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->orderBy('created_at', 'asc');
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class)->whereNull('comment_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(TicketTag::class, 'ticket_tag', 'ticket_id', 'ticket_tag_id')->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(TicketActivity::class)->orderBy('created_at', 'desc');
    }

    public function getStatusLabelAttribute(): string
    {
        return match ((string) $this->status) {
            'open' => 'باز',
            'in_progress' => 'در حال بررسی',
            'waiting' => 'در انتظار پاسخ',
            'closed' => 'بسته',
            default => (string) $this->status,
        };
    }

    public function getPriorityLabelAttribute(): string
    {
        return match ((string) $this->priority) {
            'low' => 'کم',
            'normal' => 'عادی',
            'high' => 'زیاد',
            'urgent' => 'فوری',
            default => (string) $this->priority,
        };
    }
}
