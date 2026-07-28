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
            $table->dropForeign(['announcement_id']);
            $table->dropColumn('announcement_id');
        });
    }
};
