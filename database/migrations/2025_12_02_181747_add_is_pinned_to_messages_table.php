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
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'is_pinned')) {
                $table->boolean('is_pinned')->default(false)->after('removed_by');
            }
        });
        
        // Add index for better query performance
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'is_pinned')) {
            Schema::table('messages', function (Blueprint $table) {
                $table->index(['group_id', 'is_pinned', 'removed_by']);
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
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'is_pinned')) {
                $table->dropIndex(['group_id', 'is_pinned', 'removed_by']);
                $table->dropColumn('is_pinned');
            }
        });
    }
};
