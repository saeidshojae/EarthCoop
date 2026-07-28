<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up() {
        // Ensure users.id is primary key and auto-increment unsigned big integer
        $conn = DB::connection();
        $driver = $conn->getDriverName();
        if ($driver === 'mysql') {
            // Add primary key first (required before AUTO_INCREMENT)
            $hasPk = $conn->selectOne("SELECT COUNT(1) AS c FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'users' AND CONSTRAINT_TYPE='PRIMARY KEY'", [$conn->getDatabaseName()]);
            if (!$hasPk || (int)$hasPk->c === 0) {
                DB::statement('ALTER TABLE `users` ADD PRIMARY KEY (`id`)');
            }
            // Make id auto-increment if not already
            try {
                DB::statement('ALTER TABLE `users` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            } catch (Throwable $e) {
                // Ignore if not supported or already auto-increment
            }
        } else {
            // Other drivers: rely on schema builder best-effort
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('id', true)->change();
            });
        }
    }

    public function down() {
        // No-op: reversing could be destructive
    }
};
