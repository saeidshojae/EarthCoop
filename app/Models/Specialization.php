<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Specialization extends Model
{
    use HasFactory;

    // فیلدهای قابل درج یا به‌روزرسانی
    protected $fillable = [
        'title',
        'parent_id',
        'job_field_id',
        'level',
    ];

    /**
     * ارتباط با رسته‌های صنفی
     */
    public function jobField()
    {
        return $this->belongsTo(JobField::class)->withDefault();
    }

    /**
     * ارتباط با والد
     */
    public function parent()
    {
        return $this->belongsTo(Specialization::class, 'parent_id')->withDefault();
    }

    /**
     * ارتباط با فرزندان
     */
    public function children()
    {
        return $this->hasMany(Specialization::class, 'parent_id');
    }

    /**
     * محدوده برای تخصص‌های سطح خاص
     */
    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }
}
