<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('group_user') || !Schema::hasColumn('group_user', 'role')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `group_user` MODIFY `role` VARCHAR(20) NOT NULL DEFAULT "observer"');
        }

        DB::table('group_user')->where('role', 'observer')->update(['role' => '0']);
        DB::table('group_user')->where('role', 'active')->update(['role' => '1']);
        DB::table('group_user')->whereNull('role')->update(['role' => '0']);

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `group_user` MODIFY `role` TINYINT NOT NULL DEFAULT 0');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('group_user') || !Schema::hasColumn('group_user', 'role')) {
            return;
        }

        $driver = DB::getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE `group_user` MODIFY `role` VARCHAR(20) NOT NULL DEFAULT "observer"');
        }

        DB::table('group_user')->where('role', 0)->update(['role' => 'observer']);
        DB::table('group_user')->whereIn('role', [1, 2, 3, 4, 5])->update(['role' => 'active']);

        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE `group_user` MODIFY `role` ENUM('active','observer') NOT NULL DEFAULT 'observer'");
        }
    }
};
