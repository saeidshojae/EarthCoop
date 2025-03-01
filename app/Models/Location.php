<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Location extends Model
{
    use HasFactory;

    // نام جدول مربوطه
    protected $table = 'locations';

    // فیلدهایی که می‌توانند مقداردهی شوند (Mass Assignment)
    protected $fillable = [
        'name',
        'name_en',
        'name_local',
        'code',
        'national_code',
        'parent_id',
    ];

    // رابطه برای دریافت والد
    public function parent()
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    // رابطه برای دریافت فرزندان
    public function children()
    {
        return $this->hasMany(Location::class, 'parent_id');
    }

    // متد برای واکشی سطوح بر اساس parent_id
    public static function getByParentId($parentId)
    {
        return self::where('parent_id', $parentId)->get();
    }

    // متد برای واکشی تمام والدین یک مکان (تا بالاترین سطح)
    public function getParents()
    {
        $parents = collect([]);
        $current = $this;

        while ($current->parent) {
            $parents->push($current->parent);
            $current = $current->parent;
        }

        return $parents->reverse(); // بازگشت لیست والدین از بالاترین سطح به پایین
    }

    // متد برای واکشی تمام فرزندان یک مکان (تا پایین‌ترین سطح)
    public function getDescendants()
    {
        $descendants = collect([]);

        foreach ($this->children as $child) {
            $descendants->push($child);
            $descendants = $descendants->merge($child->getDescendants());
        }

        return $descendants;
    }

    // متد برای واکشی مسیر کامل یک مکان (از بالاترین سطح تا خود مکان)
    public function getFullPath($separator = ' > ')
    {
        $path = [];
        $current = $this;

        while ($current) {
            $path[] = $current->name;
            $current = $current->parent;
        }

        return implode($separator, array_reverse($path));
    }

    // متد برای واکشی سطوح بر اساس نوع (مثلاً قاره، کشور، استان، و غیره)
    public static function getByType($type, $parentId = null)
    {
        $query = self::query();

        switch ($type) {
            case 'continent':
                $query->whereNull('parent_id');
                break;
            case 'country':
                $query->whereHas('parent', function ($q) {
                    $q->whereNull('parent_id');
                });
                break;
            case 'province':
                $query->whereHas('parent', function ($q) {
                    $q->whereHas('parent', function ($q) {
                        $q->whereNull('parent_id');
                    });
                });
                break;
            // ادامه برای سایر سطوح...
            default:
                if ($parentId) {
                    $query->where('parent_id', $parentId);
                }
                break;
        }

        return $query->get();
    }
}