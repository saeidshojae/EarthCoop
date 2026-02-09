<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Poll extends Model
{
    use HasFactory;
    protected $fillable = [
        'group_id',
        'question',
        'is_multiple',
        'is_anonymous',
        'is_active',
        'show_results',
        'expires_at',
        'created_by',
        'type',
        'skill_id',
        'main_type'
    ];

    public function skill(){
        return $this->belongsTo(ExperienceField::class, 'skill_id');
    }

    // ارتباط با گروه
    public function group()
    {
        return $this->belongsTo(Group::class);
    }
    
        public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // کاربری که نظرسنجی رو ساخته
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // گزینه‌ها
    public function options()
    {
        return $this->hasMany(PollOption::class);
    }

    // آرا
    public function votes()
    {
        return $this->hasMany(PollVote::class);
    }

    // چک کن ببین نظرسنجی منقضی شده؟
    public function isExpired()
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function yourVote()
    {
        return $this->hasMany(PollVote::class, 'poll_id') // کلید خارجی پیش‌فرض
                    ->where('user_id', auth()->id());   // فیلتر با فیلد درست
    }

}
