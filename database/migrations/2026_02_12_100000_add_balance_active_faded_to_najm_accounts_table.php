<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('najm_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('najm_accounts', 'balance_active')) {
                $table->bigInteger('balance_active')->default(0)->after('balance');
            }
            if (!Schema::hasColumn('najm_accounts', 'balance_faded')) {
                $table->bigInteger('balance_faded')->default(0)->after('balance_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('najm_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('najm_accounts', 'balance_active')) {
                $table->dropColumn('balance_active');
            }
            if (Schema::hasColumn('najm_accounts', 'balance_faded')) {
                $table->dropColumn('balance_faded');
            }
        });
    }
};
