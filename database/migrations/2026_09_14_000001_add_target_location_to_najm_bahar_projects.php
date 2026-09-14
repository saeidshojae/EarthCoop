<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            $table->foreignId('target_location_id')
                ->nullable()
                ->after('governance_area_id')
                ->constrained('locations')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('target_location_id');
        });
    }
};
