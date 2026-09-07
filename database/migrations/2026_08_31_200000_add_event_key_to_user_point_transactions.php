<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_point_transactions', function (Blueprint $table) {
            $table->string('event_key', 191)->nullable()->after('reference_id');
            $table->unique('event_key', 'user_point_transactions_event_key_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user_point_transactions', function (Blueprint $table) {
            $table->dropUnique('user_point_transactions_event_key_unique');
            $table->dropColumn('event_key');
        });
    }
};
