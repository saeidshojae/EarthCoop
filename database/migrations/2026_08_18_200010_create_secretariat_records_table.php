<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained('secretariat_offices')->restrictOnDelete();
            $table->string('registry_number', 160)->nullable();
            $table->unsignedBigInteger('registry_sequence')->nullable();
            $table->unsignedInteger('registry_year')->nullable();
            $table->string('registry_family', 48)->nullable();
            $table->string('record_type', 64);
            $table->string('direction', 24)->default('none');
            $table->string('title', 500);
            $table->string('subject', 500)->nullable();
            $table->text('summary')->nullable();
            $table->string('status', 32)->default('draft');
            $table->string('confidentiality', 32);
            $table->unsignedBigInteger('current_version_id')->nullable();
            $table->foreignId('registered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('registered_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('effective_at')->nullable();
            $table->string('source_type', 64)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['office_id', 'registry_number'], 'secretariat_records_number_unique');
            $table->unique(
                ['office_id', 'registry_year', 'registry_family', 'registry_sequence'],
                'secretariat_records_sequence_unique'
            );
            $table->index(['office_id', 'status'], 'secretariat_records_office_status_idx');
            $table->index(['office_id', 'record_type'], 'secretariat_records_office_type_idx');
            $table->index(['source_type', 'source_id'], 'secretariat_records_source_idx');
            $table->index('registered_at', 'secretariat_records_registered_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_records');
    }
};
