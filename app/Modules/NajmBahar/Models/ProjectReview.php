<?php

namespace App\Modules\NajmBahar\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProjectReview extends Model
{
    protected $table = 'najm_bahar_project_reviews';

    protected $fillable = [
        'project_id',
        'reviewer_id',
        'action',
        'comment',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * پروژه مرتبط
     */
    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * کاربر بررسی‌کننده (ادمین)
     */
    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /**
     * دریافت نام فارسی اقدام
     */
    public function getActionLabelAttribute(): string
    {
        return match($this->action) {
            'submitted' => 'ارسال شده',
            'under_review' => 'در حال بررسی',
            'approved' => 'تایید شده',
            'rejected' => 'رد شده',
            'revision_requested' => 'درخواست اصلاح',
            'resubmitted' => 'ارسال مجدد',
            'archived' => 'بایگانی شده',
            'assigned' => 'ارجاع شده برای بررسی',
            'assignment_completed' => 'بررسی ارجاع شده تکمیل شد',
            'assignment_rejected' => 'بررسی ارجاع شده رد شد',
            default => 'نامشخص',
        };
    }

    /**
     * دریافت رنگ badge برای اقدام
     */
    public function getActionColorAttribute(): string
    {
        return match($this->action) {
            'approved' => 'green',
            'rejected' => 'red',
            'under_review' => 'blue',
            'submitted', 'resubmitted' => 'purple',
            'revision_requested' => 'amber',
            'archived' => 'gray',
            default => 'slate',
        };
    }
}
