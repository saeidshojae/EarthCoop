<?php

namespace App\Modules\NajmBahar\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProjectCategory extends Model
{
    use HasFactory;
    protected $table = 'najm_bahar_project_categories';

    protected $fillable = [
        'name',
        'parent_id',
        'level',
        'order',
        'status',
        'description',
    ];

    protected $casts = [
        'status' => 'boolean',
        'level' => 'integer',
        'order' => 'integer',
    ];

    /**
     * دسته‌بندی والد
     */
    public function parent()
    {
        return $this->belongsTo(ProjectCategory::class, 'parent_id');
    }

    /**
     * دسته‌بندی‌های فرزند
     */
    public function children()
    {
        return $this->hasMany(ProjectCategory::class, 'parent_id')->orderBy('order');
    }

    /**
     * پروژه‌های سطح 1 (صنعت)
     */
    public function projectsLevel1()
    {
        return $this->hasMany(Project::class, 'category_level1_id');
    }

    /**
     * پروژه‌های سطح 2 (زیرصنعت)
     */
    public function projectsLevel2()
    {
        return $this->hasMany(Project::class, 'category_level2_id');
    }

    /**
     * پروژه‌های سطح 3 (نوع پروژه)
     */
    public function projectsLevel3()
    {
        return $this->hasMany(Project::class, 'category_level3_id');
    }

    /**
     * اسکوپ برای دسته‌بندی‌های فعال
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * اسکوپ برای دسته‌بندی‌های سطح خاص
     */
    public function scopeLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    /**
     * اسکوپ برای دسته‌بندی‌های ریشه (بدون والد)
     */
    public function scopeRoot($query)
    {
        return $query->whereNull('parent_id');
    }

    /**
     * دریافت مسیر کامل دسته‌بندی
     */
    public function getFullPathAttribute(): string
    {
        $path = collect([$this->name]);
        $parent = $this->parent;

        while ($parent) {
            $path->prepend($parent->name);
            $parent = $parent->parent;
        }

        return $path->implode(' / ');
    }

    /**
     * تعریف Factory برای تست
     */
    protected static function newFactory()
    {
        return \Database\Factories\Modules\NajmBahar\Models\ProjectCategoryFactory::new();
    }
}
