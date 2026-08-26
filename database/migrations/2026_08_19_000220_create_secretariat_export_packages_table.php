<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_export_packages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_version_id')->constrained('secretariat_record_versions')->restrictOnDelete();
            $table->foreignId('integrity_manifest_id')->constrained('secretariat_integrity_manifests')->restrictOnDelete();
            $table->unsignedInteger('package_sequence');
            $table->string('format', 30)->default('zip');
            $table->string('storage_disk', 100);
            $table->string('storage_key', 512);
            $table->unsignedBigInteger('file_size');
            $table->char('package_checksum', 64);
            $table->json('package_manifest');
            $table->foreignId('generated_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->unique(['record_version_id', 'package_sequence'], 'secretariat_export_version_seq_uq');
            $table->unique(['storage_disk', 'storage_key'], 'secretariat_export_storage_key_uq');
            $table->index(['integrity_manifest_id', 'package_checksum'], 'secretariat_export_manifest_checksum_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_export_packages');
    }
};
