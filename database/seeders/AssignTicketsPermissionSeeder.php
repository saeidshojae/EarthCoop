<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AssignTicketsPermissionSeeder extends Seeder
{
    public function run()
    {
        if (!Schema::hasTable('permissions')) {
            $this->command->info('permissions table does not exist; skipping AssignTicketsPermissionSeeder');
            return;
        }

        $permission = DB::table('permissions')->where('name', 'tickets.manage')->first();
        if (!$permission) {
            $permissionData = ['name' => 'tickets.manage'];
            if (Schema::hasColumn('permissions', 'guard_name')) {
                $permissionData['guard_name'] = 'web';
            }
            if (Schema::hasColumn('permissions', 'slug')) {
                $permissionData['slug'] = 'tickets.manage';
            }

            $permissionId = DB::table('permissions')->insertGetId($permissionData);
            $this->command->info('Created permission tickets.manage');
        } else {
            $permissionId = $permission->id;
            $this->command->info('Permission tickets.manage already exists');
        }

        if (!Schema::hasTable('roles')) {
            $this->command->info('roles table does not exist; skipping role assignment');
            return;
        }

        $patterns = ['%support%', '%helpdesk%', '%customer-support%', '%support-team%'];
        $roles = DB::table('roles')->where(function($q) use ($patterns) {
            foreach ($patterns as $p) {
                $q->orWhere('slug', 'like', $p)->orWhere('name', 'like', $p);
            }
        })->get();

        if ($roles->isEmpty()) {
            $this->command->info('No roles matched support patterns; no assignments made');
            return;
        }

        foreach ($roles as $role) {
            if (Schema::hasTable('role_has_permissions')) {
                $exists = DB::table('role_has_permissions')
                    ->where('permission_id', $permissionId)
                    ->where('role_id', $role->id)
                    ->first();
                if (!$exists) {
                    DB::table('role_has_permissions')->insert([
                        'permission_id' => $permissionId,
                        'role_id' => $role->id,
                    ]);
                    $this->command->info("Assigned tickets.manage to role {$role->name}");
                } else {
                    $this->command->info("Role {$role->name} already has tickets.manage");
                }
            }
        }
    }
}
