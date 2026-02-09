<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Common profile visibility toggles used across the UI/controllers
            if (!Schema::hasColumn('users', 'show_name')) {
                $table->boolean('show_name')->default(true);
            }
            if (!Schema::hasColumn('users', 'show_email')) {
                $table->boolean('show_email')->default(true);
            }
            if (!Schema::hasColumn('users', 'show_phone')) {
                $table->boolean('show_phone')->default(true);
            }
            if (!Schema::hasColumn('users', 'show_birthdate')) {
                $table->boolean('show_birthdate')->default(true);
            }

            // Columns currently causing "Unknown column" errors in ProfileController@showInfo
            if (!Schema::hasColumn('users', 'show_gender')) {
                $table->boolean('show_gender')->default(true);
            }
            if (!Schema::hasColumn('users', 'show_national_id')) {
                $table->boolean('show_national_id')->default(true);
            }
            if (!Schema::hasColumn('users', 'show_groups')) {
                $table->boolean('show_groups')->default(true);
            }
            if (!Schema::hasColumn('users', 'show_created_at')) {
                $table->boolean('show_created_at')->default(true);
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = [
                'show_name',
                'show_email',
                'show_phone',
                'show_birthdate',
                'show_gender',
                'show_national_id',
                'show_groups',
                'show_created_at',
            ];

            foreach ($cols as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};


