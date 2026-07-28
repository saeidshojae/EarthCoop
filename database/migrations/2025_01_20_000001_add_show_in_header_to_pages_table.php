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
        if (!Schema::hasTable('pages') || Schema::hasColumn('pages', 'show_in_header')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) {
            $table->boolean('show_in_header')->default(false)->after('is_published');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('pages') || !Schema::hasColumn('pages', 'show_in_header')) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) {
            $table->dropColumn('show_in_header');
        });
    }
};

