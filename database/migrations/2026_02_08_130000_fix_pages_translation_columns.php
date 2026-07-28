<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pages')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) {
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
                'meta_description_translations',
            ] as $column) {
                if (Schema::hasColumn('pages', $column)) {
                    $drop[] = $column;
                }
            }

            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};
