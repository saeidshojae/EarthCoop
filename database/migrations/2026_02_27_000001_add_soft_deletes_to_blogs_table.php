<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $targetTable = Schema::hasTable('blogs') ? 'blogs' : (Schema::hasTable('blog_posts') ? 'blog_posts' : null);
        if ($targetTable === null) {
            return;
        }

        if (!Schema::hasColumn($targetTable, 'deleted_at')) {
            Schema::table($targetTable, function (Blueprint $table) {
                $table->softDeletes();
            });
        }
    }

    public function down(): void
    {
        $targetTable = Schema::hasTable('blogs') ? 'blogs' : (Schema::hasTable('blog_posts') ? 'blog_posts' : null);
        if ($targetTable === null) {
            return;
        }

        if (Schema::hasColumn($targetTable, 'deleted_at')) {
            Schema::table($targetTable, function (Blueprint $table) {
                $table->dropSoftDeletes();
            });
        }
    }
};
