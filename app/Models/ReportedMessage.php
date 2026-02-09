<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportedMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'message_id',
        'reported_by',
        'reason',
        'status',
        'admin_note',
        'group_id',
        'description',
        'manager_note',
        'reviewed_by_manager',
        'reviewed_at',
        'escalated_to_admin',
        'escalated_at'
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
        'escalated_at' => 'datetime',
        'escalated_to_admin' => 'boolean'
    ];

    public function message()
    {
        return $this->belongsTo(Message::class);
    }

    public function reporter()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }

    public function reviewedByManager()
    {
        return $this->belongsTo(User::class, 'reviewed_by_manager');
    }

    /**
     * بررسی اینکه آیا گزارش در انتظار بررسی مدیر گروه است
     */
    public function isPendingGroupManager()
    {
        return $this->status === 'pending_group_manager';
    }

    /**
     * بررسی اینکه آیا گزارش به ادمین ارجاع شده است
     */
    public function isEscalatedToAdmin()
    {
        return $this->escalated_to_admin || $this->status === 'escalated_to_admin';
    }

    /**
     * بررسی اینکه آیا گزارش حل شده است
     */
    public function isResolved()
    {
        return in_array($this->status, ['resolved_by_group_manager', 'resolved_by_admin']);
    }
} 