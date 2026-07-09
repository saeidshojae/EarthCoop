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
                $table->boolean('is_pinned')->default(false);
            }
        });
        
        // Add index for better query performance
        if (Schema::hasTable('messages') && Schema::hasColumn('messages', 'is_pinned')) {
            Schema::table('messages', function (Blueprint $table) {
                if (Schema::hasColumn('messages', 'removed_by')) {
                    $table->index(['group_id', 'is_pinned', 'removed_by']);
                } else {
                    $table->index(['group_id', 'is_pinned']);
                }
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
                try {
                    if (Schema::hasColumn('messages', 'removed_by')) {
                        $table->dropIndex(['group_id', 'is_pinned', 'removed_by']);
                    } else {
                        $table->dropIndex(['group_id', 'is_pinned']);
                    }
                } catch (\Throwable $e) {
                    // Ignore if the index does not exist.
                }
                $table->dropColumn('is_pinned');
            }
        });
    }
};
