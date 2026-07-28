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
        // If pages table doesn't exist (test environments or different migration ordering), skip safely
        if (!Schema::hasTable('pages')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) {
            // Add JSON columns for translations
            if (!Schema::hasColumn('pages', 'title_translations')) {
                $table->json('title_translations')->nullable()->after('title');
            }
            if (!Schema::hasColumn('pages', 'content_translations')) {
                $table->json('content_translations')->nullable()->after('content');
            }
            if (!Schema::hasColumn('pages', 'meta_title_translations')) {
                $table->json('meta_title_translations')->nullable()->after('meta_title');
            }
            if (!Schema::hasColumn('pages', 'meta_description_translations')) {
                $table->json('meta_description_translations')->nullable()->after('meta_description');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) {
            $drop = [];
            foreach ([
                'title_translations',
                'content_translations',
                'meta_title_translations',
                'meta_description_translations'
            ] as $col) {
                if (Schema::hasColumn('pages', $col)) {
                    $drop[] = $col;
                }
            }
            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};


