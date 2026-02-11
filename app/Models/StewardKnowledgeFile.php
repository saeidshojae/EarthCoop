<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class StewardKnowledgeFile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'original_filename',
        'file_path',
        'file_type',
        'file_size',
        'extracted_content',
        'summary',
        'is_active',
        'search_priority',
        'uploaded_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'file_size' => 'integer',
        'search_priority' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * رابطه با کاربر آپلودکننده
     */
    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Scope برای فایل‌های فعال
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * جستجو در عنوان و محتوای استخراج‌شده
     */
    public function scopeSearch($query, $keywords)
    {
        return $query->where(function($q) use ($keywords) {
            $q->where('title', 'like', "%{$keywords}%")
              ->orWhere('extracted_content', 'like', "%{$keywords}%");
        });
    }

    /**
     * دریافت حجم فایل به‌صورت قابل خواندن
     */
    public function getFormattedFileSizeAttribute()
    {
        $bytes = $this->file_size;
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return round($bytes / 1024, 2) . ' KB';
        }
        return $bytes . ' Bytes';
    }

    /**
     * دریافت آیکون بر اساس نوع فایل
     */
    public function getFileIconAttribute()
    {
        $icons = [
            'pdf' => 'fa-file-pdf',
            'doc' => 'fa-file-word',
            'docx' => 'fa-file-word',
            'txt' => 'fa-file-alt',
            'md' => 'fa-file-code',
            'default' => 'fa-file'
        ];

        return $icons[$this->file_type] ?? $icons['default'];
    }
}
