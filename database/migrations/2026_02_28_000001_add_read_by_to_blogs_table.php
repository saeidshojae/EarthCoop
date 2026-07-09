<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $targetTable = Schema::hasTable('blogs') ? 'blogs' : (Schema::hasTable('blog_posts') ? 'blog_posts' : null);
        if ($targetTable === null) {
            return;
        }

        if (!Schema::hasColumn($targetTable, 'read_by')) {
            Schema::table($targetTable, function (Blueprint $table) use ($targetTable) {
                $column = $table->json('read_by')->nullable()->comment('JSON array of user_id => timestamp');
                if (Schema::hasColumn($targetTable, 'file_type')) {
                    $column->after('file_type');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $targetTable = Schema::hasTable('blogs') ? 'blogs' : (Schema::hasTable('blog_posts') ? 'blog_posts' : null);
        if ($targetTable === null) {
            return;
        }

        if (Schema::hasColumn($targetTable, 'read_by')) {
            Schema::table($targetTable, function (Blueprint $table) {
                $table->dropColumn('read_by');
            });
        }
    }
};
