<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('governance_area_id')->nullable()->after('address_id')->constrained('governance_areas')->nullOnDelete();
            $table->string('dimension_key', 64)->nullable()->after('governance_area_id');
            $table->string('dimension_value_key', 191)->nullable()->after('dimension_key');
            $table->unique(
                ['governance_area_id', 'dimension_key', 'dimension_value_key'],
                'groups_canonical_scope_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropUnique('groups_canonical_scope_unique');
            $table->dropForeign(['governance_area_id']);
            $table->dropColumn(['governance_area_id', 'dimension_key', 'dimension_value_key']);
        });
    }
};
