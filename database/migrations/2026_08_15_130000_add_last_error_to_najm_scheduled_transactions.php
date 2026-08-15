<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('najm_scheduled_transactions')
            || Schema::hasColumn('najm_scheduled_transactions', 'last_error')) {
            return;
        }

        Schema::table('najm_scheduled_transactions', function (Blueprint $table) {
            $table->text('last_error')->nullable()->after('attempts');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('najm_scheduled_transactions')
            || ! Schema::hasColumn('najm_scheduled_transactions', 'last_error')) {
            return;
        }

        Schema::table('najm_scheduled_transactions', function (Blueprint $table) {
            $table->dropColumn('last_error');
        });
    }
};
