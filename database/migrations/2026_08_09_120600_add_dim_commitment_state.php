<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('najm_accounts', function (Blueprint $table) {
            $table->unsignedBigInteger('committed_dim')->default(0)->after('balance_faded');
        });

        Schema::table('governance_public_contribution_obligations', function (Blueprint $table) {
            $table->unsignedBigInteger('committed_gol')->default(0)->after('paid_gol');
            $table->timestamp('committed_at')->nullable()->after('due_at');
        });
    }

    public function down(): void
    {
        Schema::table('governance_public_contribution_obligations', function (Blueprint $table) {
            $table->dropColumn(['committed_gol', 'committed_at']);
        });

        Schema::table('najm_accounts', function (Blueprint $table) {
            $table->dropColumn('committed_dim');
        });
    }
};
