<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_integrity_manifests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_version_id')->constrained('secretariat_record_versions')->cascadeOnDelete();
            $table->unsignedInteger('manifest_sequence');
            $table->char('manifest_checksum', 64);
            $table->json('payload');
            $table->foreignId('generated_by')->constrained('users');
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['record_version_id', 'manifest_sequence'], 'secretariat_integrity_manifest_seq_uq');
            $table->index(['record_version_id', 'manifest_checksum'], 'secretariat_integrity_manifest_checksum_idx');
        });

        Schema::create('secretariat_signature_attestations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('manifest_id')->constrained('secretariat_integrity_manifests')->restrictOnDelete();
            $table->foreignId('contract_signatory_id')->nullable()->constrained('secretariat_contract_signatories')->restrictOnDelete();
            $table->enum('attestation_type', ['signature', 'seal']);
            $table->string('provider', 120);
            $table->string('provider_reference', 255)->nullable();
            $table->string('signer_name_snapshot', 255);
            $table->char('signer_identifier_hash', 64)->nullable();
            $table->enum('verification_status', ['recorded', 'verified', 'rejected'])->default('recorded');
            $table->timestamp('verified_at')->nullable();
            $table->json('evidence_metadata')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->timestamps();

            $table->index(['manifest_id', 'verification_status'], 'secretariat_signature_manifest_status_idx');
            $table->index(['provider', 'provider_reference'], 'secretariat_signature_provider_ref_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_signature_attestations');
        Schema::dropIfExists('secretariat_integrity_manifests');
    }
};
