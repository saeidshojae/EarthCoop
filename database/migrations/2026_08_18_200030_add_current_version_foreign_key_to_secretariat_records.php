<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('secretariat_records', function (Blueprint $table) {
            $table->foreign('current_version_id', 'secretariat_records_current_version_fk')
                ->references('id')
                ->on('secretariat_record_versions')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('secretariat_records', function (Blueprint $table) {
            $table->dropForeign('secretariat_records_current_version_fk');
        });
    }
};
