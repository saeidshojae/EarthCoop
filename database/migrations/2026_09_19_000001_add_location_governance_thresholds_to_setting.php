<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('setting', function (Blueprint $table): void {
            $table->unsignedInteger('location_proposal_verification_threshold')->default(10);
            $table->unsignedInteger('location_structure_claim_verification_threshold')->default(10);
        });
    }

    public function down(): void
    {
        Schema::table('setting', function (Blueprint $table): void {
            $table->dropColumn([
                'location_proposal_verification_threshold',
                'location_structure_claim_verification_threshold',
            ]);
        });
    }
};
