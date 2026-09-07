<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_record_id')->constrained('secretariat_records')->cascadeOnDelete();
            $table->foreignId('target_record_id')->constrained('secretariat_records')->cascadeOnDelete();
            $table->string('relation_type', 48);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['source_record_id', 'target_record_id', 'relation_type'],
                'secretariat_relations_direction_unique'
            );
            $table->index(['source_record_id', 'relation_type'], 'secretariat_relations_source_type_idx');
            $table->index(['target_record_id', 'relation_type'], 'secretariat_relations_target_type_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_relations');
    }
};
