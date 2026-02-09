<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketActivity extends Model
{
    protected $fillable = [
        'ticket_id',
        'user_id',
        'type',
        'field',
        'old_value',
        'new_value',
        'description',
    ];

    /**
     * Get the ticket that owns the activity
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    /**
     * Get the user who performed the activity
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get human readable activity description
     */
    public function getDescriptionAttribute($value): string
    {
        if ($value) {
            return $value;
        }

        $userName = $this->user ? $this->user->fullName() : 'سیستم';
        
        switch ($this->type) {
            case 'status_changed':
                $oldStatus = $this->getStatusLabel($this->old_value);
                $newStatus = $this->getStatusLabel($this->new_value);
                return "{$userName} وضعیت تیکت را از «{$oldStatus}» به «{$newStatus}» تغییر داد";
            
            case 'priority_changed':
                $oldPriority = $this->getPriorityLabel($this->old_value);
                $newPriority = $this->getPriorityLabel($this->new_value);
                return "{$userName} اولویت تیکت را از «{$oldPriority}» به «{$newPriority}» تغییر داد";
            
            case 'assignee_changed':
                if ($this->new_value) {
                    $assignee = \App\Models\User::find($this->new_value);
                    if ($assignee) {
                        return "{$userName} تیکت را به «{$assignee->fullName()}» اختصاص داد";
                    }
                    return "{$userName} مسئول تیکت را تغییر داد";
                } else {
                    return "{$userName} اختصاص تیکت را حذف کرد";
                }
            
            case 'comment_added':
                return "{$userName} یک پاسخ اضافه کرد";
            
            case 'ticket_created':
                return "تیکت توسط {$userName} ایجاد شد";
            
            default:
                return "{$userName} تغییراتی در تیکت ایجاد کرد";
        }
    }

    private function getStatusLabel($status): string
    {
        return match($status) {
            'open' => 'باز',
            'in-progress' => 'در حال بررسی',
            'closed' => 'بسته شده',
            default => $status ?? 'نامشخص',
        };
    }

    private function getPriorityLabel($priority): string
    {
        return match($priority) {
            'low' => 'پایین',
            'normal' => 'عادی',
            'high' => 'بالا',
            default => $priority ?? 'نامشخص',
        };
    }
}
