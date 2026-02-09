<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        // Only run if permissions table exists (Spatie or similar)
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $exists = DB::table('permissions')->where('name', 'tickets.manage')->first();
        if (!$exists) {
            $permissionData = ['name' => 'tickets.manage'];
            if (Schema::hasColumn('permissions', 'guard_name')) {
                $permissionData['guard_name'] = 'web';
            }
            if (Schema::hasColumn('permissions', 'slug')) {
                $permissionData['slug'] = 'tickets.manage';
            }

            $permissionId = DB::table('permissions')->insertGetId($permissionData);

            // assign to roles with slug containing 'support'
            if (Schema::hasTable('roles')) {
                $roles = DB::table('roles')->where('slug', 'like', '%support%')->get();
                foreach ($roles as $role) {
                    if (Schema::hasTable('role_has_permissions')) {
                        DB::table('role_has_permissions')->insert([
                            'permission_id' => $permissionId,
                            'role_id' => $role->id,
                        ]);
                    }
                }
            }
        }
    }

    public function down()
    {
        if (!Schema::hasTable('permissions')) {
            return;
        }

        $permission = DB::table('permissions')->where('name', 'tickets.manage')->first();
        if ($permission) {
            if (Schema::hasTable('role_has_permissions')) {
                DB::table('role_has_permissions')->where('permission_id', $permission->id)->delete();
            }
            DB::table('permissions')->where('id', $permission->id)->delete();
        }
    }
};
