<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('secretariat_records')->restrictOnDelete();
            $table->foreignId('version_id')->nullable()->constrained('secretariat_record_versions')->restrictOnDelete();
            $table->string('original_name', 500);
            $table->string('storage_disk', 64);
            $table->string('storage_key', 512);
            $table->string('mime_type', 255)->nullable();
            $table->unsignedBigInteger('file_size');
            $table->char('checksum', 64);
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('uploaded_at');
            $table->string('state', 32)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['storage_disk', 'storage_key'], 'secretariat_attachments_storage_unique');
            $table->index(['record_id', 'state'], 'secretariat_attachments_record_state_idx');
            $table->index(['version_id', 'state'], 'secretariat_attachments_version_state_idx');
            $table->index(['record_id', 'checksum'], 'secretariat_attachments_record_checksum_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_attachments');
    }
};
