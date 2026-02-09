<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'module',
        'order',
    ];

    /**
     * روابط با Role ها
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'role_permission');
    }

    /**
     * Scope برای فیلتر بر اساس ماژول
     */
    public function scopeOfModule($query, $module)
    {
        return $query->where('module', $module);
    }
}
