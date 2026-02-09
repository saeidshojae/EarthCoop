<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\Permission;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // ایجاد دسترسی‌ها
        $permissions = [
            // مدیریت کاربران
            ['name' => 'مشاهده کاربران', 'slug' => 'users.view', 'description' => 'مشاهده لیست کاربران', 'module' => 'users', 'order' => 1],
            ['name' => 'ایجاد کاربر', 'slug' => 'users.create', 'description' => 'ایجاد کاربر جدید', 'module' => 'users', 'order' => 2],
            ['name' => 'ویرایش کاربر', 'slug' => 'users.edit', 'description' => 'ویرایش اطلاعات کاربر', 'module' => 'users', 'order' => 3],
            ['name' => 'حذف کاربر', 'slug' => 'users.delete', 'description' => 'حذف کاربر', 'module' => 'users', 'order' => 4],
            ['name' => 'Export کاربران', 'slug' => 'users.export', 'description' => 'خروجی Excel کاربران', 'module' => 'users', 'order' => 5],
            ['name' => 'مدیریت وضعیت کاربران', 'slug' => 'users.manage-status', 'description' => 'فعال/غیرفعال/تعلیق کاربران', 'module' => 'users', 'order' => 6],
            ['name' => 'بازنشانی رمز عبور', 'slug' => 'users.reset-password', 'description' => 'بازنشانی رمز عبور کاربر', 'module' => 'users', 'order' => 7],
            
            // مدیریت گروه‌ها
            ['name' => 'مشاهده گروه‌ها', 'slug' => 'groups.view', 'description' => 'مشاهده لیست گروه‌ها', 'module' => 'groups', 'order' => 10],
            ['name' => 'ایجاد گروه', 'slug' => 'groups.create', 'description' => 'ایجاد گروه جدید', 'module' => 'groups', 'order' => 11],
            ['name' => 'ویرایش گروه', 'slug' => 'groups.edit', 'description' => 'ویرایش اطلاعات گروه', 'module' => 'groups', 'order' => 12],
            ['name' => 'حذف گروه', 'slug' => 'groups.delete', 'description' => 'حذف گروه', 'module' => 'groups', 'order' => 13],
            ['name' => 'مدیریت اعضای گروه', 'slug' => 'groups.manage-members', 'description' => 'مدیریت اعضای گروه', 'module' => 'groups', 'order' => 14],
            ['name' => 'مدیریت تنظیمات گروه', 'slug' => 'groups.manage-settings', 'description' => 'ویرایش تنظیمات گروه', 'module' => 'groups', 'order' => 15],
            
            // مدیریت وبلاگ
            ['name' => 'مشاهده داشبورد وبلاگ', 'slug' => 'blog.view-dashboard', 'description' => 'مشاهده داشبورد مدیریت وبلاگ', 'module' => 'blog', 'order' => 20],
            ['name' => 'مشاهده پست‌ها', 'slug' => 'blog.view-posts', 'description' => 'مشاهده لیست پست‌ها', 'module' => 'blog', 'order' => 21],
            ['name' => 'ایجاد پست', 'slug' => 'blog.create-posts', 'description' => 'ایجاد پست جدید', 'module' => 'blog', 'order' => 22],
            ['name' => 'ویرایش پست', 'slug' => 'blog.edit-posts', 'description' => 'ویرایش پست', 'module' => 'blog', 'order' => 23],
            ['name' => 'حذف پست', 'slug' => 'blog.delete-posts', 'description' => 'حذف پست', 'module' => 'blog', 'order' => 24],
            ['name' => 'مدیریت نظرات', 'slug' => 'blog.manage-comments', 'description' => 'مدیریت نظرات وبلاگ', 'module' => 'blog', 'order' => 25],
            ['name' => 'مدیریت دسته‌بندی‌ها', 'slug' => 'blog.manage-categories', 'description' => 'مدیریت دسته‌بندی‌های وبلاگ', 'module' => 'blog', 'order' => 26],
            ['name' => 'مدیریت تگ‌ها', 'slug' => 'blog.manage-tags', 'description' => 'مدیریت تگ‌های وبلاگ', 'module' => 'blog', 'order' => 27],
            
            // مدیریت نجم هدا
            ['name' => 'مشاهده داشبورد نجم هدا', 'slug' => 'najm-hoda.view-dashboard', 'description' => 'مشاهده داشبورد نجم هدا', 'module' => 'najm-hoda', 'order' => 30],
            ['name' => 'تنظیمات نجم هدا', 'slug' => 'najm-hoda.manage-settings', 'description' => 'تنظیمات نجم هدا', 'module' => 'najm-hoda', 'order' => 31],
            ['name' => 'چت با نجم هدا', 'slug' => 'najm-hoda.chat', 'description' => 'چت با نجم هدا', 'module' => 'najm-hoda', 'order' => 32],
            ['name' => 'مشاهده مکالمات', 'slug' => 'najm-hoda.view-conversations', 'description' => 'مشاهده مکالمات نجم هدا', 'module' => 'najm-hoda', 'order' => 33],
            ['name' => 'مشاهده آمار نجم هدا', 'slug' => 'najm-hoda.analytics', 'description' => 'مشاهده آمار و گزارش‌های نجم هدا', 'module' => 'najm-hoda', 'order' => 34],
            ['name' => 'مدیریت Agent ها', 'slug' => 'najm-hoda.agents', 'description' => 'ایجاد و مدیریت Agent های نجم هدا', 'module' => 'najm-hoda', 'order' => 35],
            ['name' => 'Code Scanner', 'slug' => 'najm-hoda.use-code-scanner', 'description' => 'استفاده از Code Scanner', 'module' => 'najm-hoda', 'order' => 36],
            
            // مدیریت سهام
            ['name' => 'مشاهده داشبورد سهام', 'slug' => 'stock.view-dashboard', 'description' => 'مشاهده داشبورد مدیریت سهام', 'module' => 'stock', 'order' => 40],
            ['name' => 'مشاهده سهام', 'slug' => 'stock.view', 'description' => 'مشاهده لیست سهام', 'module' => 'stock', 'order' => 41],
            ['name' => 'ایجاد سهام', 'slug' => 'stock.create', 'description' => 'ایجاد سهام جدید', 'module' => 'stock', 'order' => 42],
            ['name' => 'ویرایش سهام', 'slug' => 'stock.edit', 'description' => 'ویرایش سهام', 'module' => 'stock', 'order' => 43],
            ['name' => 'حذف سهام', 'slug' => 'stock.delete', 'description' => 'حذف سهام', 'module' => 'stock', 'order' => 44],
            ['name' => 'مدیریت حراج', 'slug' => 'auction.manage', 'description' => 'مدیریت حراج‌ها', 'module' => 'stock', 'order' => 45],
            
            // تنظیمات و گزارش‌ها
            ['name' => 'مشاهده تنظیمات', 'slug' => 'settings.view', 'description' => 'مشاهده تنظیمات سیستم', 'module' => 'settings', 'order' => 50],
            ['name' => 'ویرایش تنظیمات', 'slug' => 'settings.edit', 'description' => 'ویرایش تنظیمات سیستم', 'module' => 'settings', 'order' => 51],
            ['name' => 'مشاهده گزارش‌ها', 'slug' => 'reports.view', 'description' => 'مشاهده گزارش‌های سیستم', 'module' => 'reports', 'order' => 52],
            ['name' => 'مدیریت گزارش‌ها', 'slug' => 'reports.manage', 'description' => 'مدیریت و بررسی گزارش‌ها', 'module' => 'reports', 'order' => 53],
            
            // مدیریت نقش‌ها و دسترسی‌ها
            ['name' => 'مدیریت نقش‌ها', 'slug' => 'roles.manage', 'description' => 'مدیریت نقش‌ها و دسترسی‌ها', 'module' => 'roles', 'order' => 60],
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(
                ['slug' => $permission['slug']],
                $permission
            );
        }

        // ایجاد نقش‌ها
        $roles = [
            [
                'name' => 'Super Admin',
                'slug' => 'super-admin',
                'description' => 'دسترسی کامل به همه بخش‌های سیستم',
                'is_system' => true,
                'order' => 1,
                'permissions' => ['*'] // همه دسترسی‌ها
            ],
            [
                'name' => 'مدیر کاربران',
                'slug' => 'user-manager',
                'description' => 'مدیریت کاربران سیستم',
                'is_system' => false,
                'order' => 2,
                'permissions' => [
                    'users.view', 'users.create', 'users.edit', 'users.delete',
                    'users.export', 'users.bulk-action'
                ]
            ],
            [
                'name' => 'مدیر محتوا',
                'slug' => 'content-manager',
                'description' => 'مدیریت وبلاگ و محتوا',
                'is_system' => false,
                'order' => 3,
                'permissions' => [
                    'blog.view', 'blog.create', 'blog.edit', 'blog.delete',
                    'blog.comments', 'blog.categories', 'blog.tags'
                ]
            ],
            [
                'name' => 'مدیر گروه‌ها',
                'slug' => 'group-manager',
                'description' => 'مدیریت گروه‌ها',
                'is_system' => false,
                'order' => 4,
                'permissions' => [
                    'groups.view', 'groups.create', 'groups.edit', 'groups.delete',
                    'groups.manage'
                ]
            ],
            [
                'name' => 'پشتیبان',
                'slug' => 'support',
                'description' => 'دسترسی به گزارش‌ها و پشتیبانی',
                'is_system' => false,
                'order' => 5,
                'permissions' => [
                    'users.view', 'reports.view', 'reports.manage'
                ]
            ],
            [
                'name' => 'ناظر',
                'slug' => 'moderator',
                'description' => 'تایید و رد محتوا و نظرات',
                'is_system' => false,
                'order' => 6,
                'permissions' => [
                    'blog.view', 'blog.comments', 'groups.view', 'reports.view', 'reports.manage'
                ]
            ],
        ];

        foreach ($roles as $roleData) {
            $permissions = $roleData['permissions'];
            unset($roleData['permissions']);
            
            $role = Role::firstOrCreate(
                ['slug' => $roleData['slug']],
                $roleData
            );

            // اگر همه دسترسی‌ها را می‌خواهد
            if ($permissions === ['*']) {
                $role->permissions()->sync(Permission::pluck('id'));
            } else {
                // اختصاص دسترسی‌های مشخص
                $permissionIds = Permission::whereIn('slug', $permissions)->pluck('id');
                $role->permissions()->sync($permissionIds);
            }
        }

        $this->command->info('Roles and Permissions seeded successfully!');
    }
}
