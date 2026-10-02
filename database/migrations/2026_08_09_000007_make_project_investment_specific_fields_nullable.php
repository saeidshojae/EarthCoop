<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // These fields belong only to the auction-shares funding method.
        // Capital-participation projects intentionally store NULL here.
        Schema::table('najm_bahar_projects', function (Blueprint $table): void {
            $table->unsignedInteger('total_shares')->nullable()->change();
            $table->decimal('initial_auction_percent', 5, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        // Historical behavior used defaults for every project, even when the
        // project did not use auction shares. Restore those defaults on rollback.
        DB::table('najm_bahar_projects')
            ->whereNull('total_shares')
            ->update(['total_shares' => 100]);
        DB::table('najm_bahar_projects')
            ->whereNull('initial_auction_percent')
            ->update(['initial_auction_percent' => 10.00]);

        Schema::table('najm_bahar_projects', function (Blueprint $table): void {
            $table->unsignedInteger('total_shares')->default(100)->change();
            $table->decimal('initial_auction_percent', 5, 2)->default(10.00)->change();
        });
    }
};
