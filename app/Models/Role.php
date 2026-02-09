<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_system',
        'order',
    ];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    /**
     * روابط با Permission ها
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'role_permission');
    }

    /**
     * روابط با User ها
     */
    public function users()
    {
        return $this->belongsToMany(User::class, 'user_role');
    }

    /**
     * بررسی اینکه آیا نقش دارای دسترسی خاصی است
     */
    public function hasPermission($permission)
    {
        if (is_string($permission)) {
            return $this->permissions()->where('slug', $permission)->exists();
        }
        
        return $this->permissions->contains($permission);
    }

    /**
     * اضافه کردن دسترسی به نقش
     */
    public function givePermission($permission)
    {
        if (is_string($permission)) {
            $permission = Permission::where('slug', $permission)->first();
        }
        
        if ($permission && !$this->hasPermission($permission)) {
            $this->permissions()->attach($permission);
        }
        
        return $this;
    }

    /**
     * حذف دسترسی از نقش
     */
    public function revokePermission($permission)
    {
        if (is_string($permission)) {
            $permission = Permission::where('slug', $permission)->first();
        }
        
        if ($permission) {
            $this->permissions()->detach($permission);
        }
        
        return $this;
    }

    /**
     * همگام‌سازی دسترسی‌ها
     */
    public function syncPermissions(array $permissions)
    {
        $permissionIds = [];
        
        foreach ($permissions as $permission) {
            if (is_string($permission)) {
                $perm = Permission::where('slug', $permission)->first();
                if ($perm) {
                    $permissionIds[] = $perm->id;
                }
            } else {
                $permissionIds[] = $permission;
            }
        }
        
        $this->permissions()->sync($permissionIds);
        
        return $this;
    }
}
