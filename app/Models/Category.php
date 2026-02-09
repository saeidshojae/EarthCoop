<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = ['name'];

    /**
     * ارتباط با تنظیمات گروه‌ها (فقط گروه‌هایی که شناسه معتبر دارند)
     */
    public function groupSettings()
    {
        return $this->belongsToMany(GroupSetting::class, 'category_group_setting', 'category_id', 'group_setting_id');
    }

    /**
     * ارتباط مستقیم با رکوردهای میانی (برای پشتیبانی از مقدار 0 = همه گروه‌ها)
     */
    public function groupSettingLinks()
    {
        return $this->hasMany(CategoryGroupSetting::class);
    }

    /**
     * بررسی می‌کند آیا این دسته برای همه گروه‌ها فعال است یا خیر.
     */
    public function appliesToAllGroups(): bool
    {
        if ($this->relationLoaded('groupSettingLinks')) {
            return $this->groupSettingLinks->contains('group_setting_id', 0);
        }

        return $this->groupSettingLinks()->where('group_setting_id', 0)->exists();
    }
}
