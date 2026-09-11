<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            $table->foreignId('governance_area_id')
                ->nullable()
                ->after('owner_id')
                ->constrained('governance_areas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('governance_area_id');
        });
    }
};
