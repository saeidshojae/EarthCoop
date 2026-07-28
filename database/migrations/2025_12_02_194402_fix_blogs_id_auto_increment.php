<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (Schema::hasTable('blogs')) {
            $conn = DB::connection();
            $driver = $conn->getDriverName();
            if ($driver === 'mysql') {
                // بررسی وجود primary key
                $hasPk = $conn->selectOne("SELECT COUNT(1) AS c FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'blogs' AND CONSTRAINT_TYPE='PRIMARY KEY'");
                if (!$hasPk || (int)$hasPk->c === 0) {
                    DB::statement('ALTER TABLE `blogs` ADD PRIMARY KEY (`id`)');
                }
                // تنظیم auto-increment
                try {
                    DB::statement('ALTER TABLE `blogs` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
                } catch (\Throwable $e) {
                    // Ignore if not supported or already auto-increment
                }
            } else {
                Schema::table('blogs', function (Blueprint $table) {
                    $table->unsignedBigInteger('id', true)->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No need to reverse this change
    }
};
