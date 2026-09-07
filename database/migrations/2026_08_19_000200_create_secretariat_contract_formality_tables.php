<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_contract_version_details', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_version_id')->unique()->constrained('secretariat_record_versions')->cascadeOnDelete();
            $table->timestamp('effective_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->enum('renewal_mode', ['none', 'manual', 'automatic'])->default('none');
            $table->unsignedInteger('renewal_notice_days')->nullable();
            $table->string('governing_law', 255)->nullable();
            $table->string('jurisdiction', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['effective_at', 'expires_at'], 'secretariat_contract_term_dates_idx');
        });

        Schema::create('secretariat_contract_signatories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_version_id')->constrained('secretariat_record_versions')->cascadeOnDelete();
            $table->foreignId('party_id')->constrained('secretariat_parties')->restrictOnDelete();
            $table->string('capacity', 255);
            $table->string('title', 255)->nullable();
            $table->unsignedSmallInteger('signing_order')->default(1);
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->unique(['record_version_id', 'party_id'], 'secretariat_contract_version_party_uq');
            $table->index(['record_version_id', 'signing_order'], 'secretariat_contract_signing_order_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_contract_signatories');
        Schema::dropIfExists('secretariat_contract_version_details');
    }
};
