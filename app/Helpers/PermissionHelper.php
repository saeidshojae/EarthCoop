<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\Role;
use App\Models\Permission;

class PermissionHelper
{
    /**
     * بررسی دسترسی کاربر
     */
    public static function hasPermission(User $user, string $permission): bool
    {
        return $user->hasPermission($permission);
    }

    /**
     * بررسی نقش کاربر
     */
    public static function hasRole(User $user, string $role): bool
    {
        return $user->hasRole($role);
    }

    /**
     * بررسی اینکه آیا کاربر Super Admin است
     */
    public static function isSuperAdmin(User $user): bool
    {
        return $user->is_admin || $user->hasRole('super-admin');
    }
}

