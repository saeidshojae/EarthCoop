<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_runs', function (Blueprint $table): void {
            $table->timestamp('scheduled_for')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('communication_runs', function (Blueprint $table): void {
            $table->dropIndex(['scheduled_for']);
            $table->dropColumn('scheduled_for');
        });
    }
};
