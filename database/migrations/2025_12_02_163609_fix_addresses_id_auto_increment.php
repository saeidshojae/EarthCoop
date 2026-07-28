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
        // Ensure addresses.id is primary key and auto-increment unsigned big integer
        $conn = DB::connection();
        $driver = $conn->getDriverName();
        
        if ($driver === 'mysql') {
            // Add primary key first (required before AUTO_INCREMENT)
            $hasPk = $conn->selectOne(
                "SELECT COUNT(1) AS c FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'addresses' AND CONSTRAINT_TYPE='PRIMARY KEY'",
                [$conn->getDatabaseName()]
            );
            
            if (!$hasPk || (int)$hasPk->c === 0) {
                DB::statement('ALTER TABLE `addresses` ADD PRIMARY KEY (`id`)');
            }
            
            // Make id auto-increment if not already
            try {
                DB::statement('ALTER TABLE `addresses` MODIFY `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT');
            } catch (\Throwable $e) {
                // Ignore if not supported or already auto-increment
            }
        } else {
            // Other drivers: rely on schema builder best-effort
            Schema::table('addresses', function (Blueprint $table) {
                $table->unsignedBigInteger('id', true)->change();
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        // No-op: reversing could be destructive
    }
};
