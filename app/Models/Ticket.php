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
        'category',
        'assignee_id',
        'sla_deadline',
        'name',
        'email',
        'phone',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'first_response_at' => 'datetime',
            'resolved_at' => 'datetime',
            'sla_deadline' => 'datetime',
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

    public function isOverdue(): bool
    {
        if (! $this->sla_deadline || $this->status === 'closed') {
            return false;
        }

        return now()->greaterThan($this->sla_deadline);
    }

    public function isApproachingDeadline(int $hours = 24): bool
    {
        if (! $this->sla_deadline || $this->status === 'closed' || $this->isOverdue()) {
            return false;
        }

        return now()->diffInMinutes($this->sla_deadline, false) <= ($hours * 60);
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
