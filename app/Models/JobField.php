<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JobField extends Model
{
    use HasFactory;

    // فیلدهای قابل درج یا به‌روزرسانی
    protected $fillable = [
        'title',
        'parent_id',
        'level',
    ];

    /**
     * ارتباط با کاربران
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_job_fields');
    }

    /**
     * ارتباط با والد
     */
    public function parent()
    {
        return $this->belongsTo(JobField::class, 'parent_id')->withDefault();
    }

    /**
     * ارتباط با فرزندان
     */
    public function children()
    {
        return $this->hasMany(JobField::class, 'parent_id');
    }

    /**
     * ارتباط با تخصص‌ها
     */
    public function specializations()
    {
        return $this->hasMany(Specialization::class);
    }

    /**
     * محدوده برای سطح‌های خاص
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }
}
