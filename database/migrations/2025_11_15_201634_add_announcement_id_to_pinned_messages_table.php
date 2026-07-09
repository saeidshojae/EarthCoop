<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('pinned_messages', function (Blueprint $table) {
            if (!Schema::hasColumn('pinned_messages', 'announcement_id')) {
                $table->unsignedBigInteger('announcement_id')->nullable()->after('pinned_by');
            }

            if (Schema::hasColumn('pinned_messages', 'announcement_id') && Schema::hasTable('announcements')) {
                $table->foreign('announcement_id')->references('id')->on('announcements')->onDelete('cascade');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('pinned_messages', function (Blueprint $table) {
            if (Schema::hasColumn('pinned_messages', 'announcement_id')) {
                try {
                    $table->dropForeign(['announcement_id']);
                } catch (\Throwable $e) {
                    // Foreign key may not exist in environments without announcements table.
                }

                $table->dropColumn('announcement_id');
            }
        });
    }
};
