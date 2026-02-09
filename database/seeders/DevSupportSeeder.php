<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DevSupportSeeder extends Seeder
{
    public function run(): void
    {
        // Create 'support' role if not exists
        if (! Schema::hasTable('roles')) {
            $this->command->info('roles table not present, skipping role creation.');
        } else {
            $role = DB::table('roles')->where('slug', 'support')->first();
            if (! $role) {
                $roleId = DB::table('roles')->insertGetId([
                    'name' => 'Support',
                    'slug' => 'support',
                    'description' => 'Support team role (dev seeder)',
                    'is_system' => false,
                    'order' => 100,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $this->command->info("Created role 'support' (id: {$roleId})");
            } else {
                $roleId = $role->id;
                $this->command->info("Role 'support' already exists (id: {$roleId})");
            }

            // Ensure permission 'tickets.manage' exists; if not, create it
            if (Schema::hasTable('permissions')) {
                $perm = DB::table('permissions')->where('slug', 'tickets.manage')->orWhere('name', 'tickets.manage')->first();
                if (! $perm) {
                    $permId = DB::table('permissions')->insertGetId([
                        'name' => 'tickets.manage',
                        'slug' => 'tickets.manage',
                        'description' => 'Manage support tickets (dev seeder)',
                        'module' => 'support',
                        'order' => 100,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                    $this->command->info("Created permission tickets.manage (id: {$permId})");
                } else {
                    $permId = $perm->id;
                    $this->command->info("Permission tickets.manage already exists (id: {$permId})");
                }

                // attach permission to role via role_permission table if present
                if (Schema::hasTable('role_permission')) {
                    $exists = DB::table('role_permission')->where('role_id', $roleId)->where('permission_id', $permId)->first();
                    if (! $exists) {
                        DB::table('role_permission')->insert([
                            'role_id' => $roleId,
                            'permission_id' => $permId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $this->command->info('Attached tickets.manage to support role');
                    } else {
                        $this->command->info('support role already has tickets.manage');
                    }
                }
            }

            // Create a sample support user
            if (! Schema::hasTable('users')) {
                $this->command->warn('users table not present, cannot create operator user.');
            } else {
                $email = 'support+dev@example.local';
                $user = DB::table('users')->where('email', $email)->first();
                if (! $user) {
                    $insert = [
                        'email' => $email,
                        'password' => Hash::make('password'),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];

                    // Support various user schemas: prefer first_name/last_name if present
                    if (Schema::hasColumn('users', 'first_name') && Schema::hasColumn('users', 'last_name')) {
                        $insert['first_name'] = 'Support';
                        $insert['last_name'] = 'Operator';
                    } elseif (Schema::hasColumn('users', 'name')) {
                        $insert['name'] = 'Support Operator';
                    }

                    $userId = DB::table('users')->insertGetId($insert);
                    $this->command->info("Created support user (id: {$userId}, email: {$email}, password: 'password')");
                } else {
                    $userId = $user->id;
                    $this->command->info("Support user already exists (id: {$userId})");
                }

                // assign role to user via user_role pivot
                if (Schema::hasTable('user_role')) {
                    $rel = DB::table('user_role')->where('user_id', $userId)->where('role_id', $roleId)->first();
                    if (! $rel) {
                        DB::table('user_role')->insert([
                            'user_id' => $userId,
                            'role_id' => $roleId,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);
                        $this->command->info('Assigned support role to user');
                    } else {
                        $this->command->info('User already has support role');
                    }
                }
            }
        }
    }
}
