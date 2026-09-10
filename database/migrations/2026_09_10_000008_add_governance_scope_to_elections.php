<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $table->foreignId('governance_area_id')
                ->nullable()
                ->after('group_id')
                ->constrained('governance_areas')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $table->dropForeign(['governance_area_id']);
            $table->dropColumn('governance_area_id');
        });
    }
};
