<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('governance_public_contribution_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('committed_total_gol')->default(0)->after('remainder_gol');
            $table->timestamp('fully_committed_at')->nullable()->after('opened_at');
        });
    }

    public function down(): void
    {
        Schema::table('governance_public_contribution_plans', function (Blueprint $table) {
            $table->dropColumn(['committed_total_gol', 'fully_committed_at']);
        });
    }
};
