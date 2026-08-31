<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_attachment_scans', function (Blueprint $table) {
            $table->id();
            // Attachment hard-delete is already restricted to draft/cancelled records
            // by the parent aggregate. Scan evidence may follow that allowed draft
            // cleanup, while formal attachment history remains undeletable.
            $table->foreignId('attachment_id')->constrained('secretariat_attachments')->cascadeOnDelete();
            $table->enum('status', ['clean', 'infected', 'unavailable', 'error']);
            $table->string('engine', 120);
            $table->string('signature', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->index(['attachment_id', 'scanned_at'], 'secretariat_attachment_scans_attachment_time_idx');
            $table->index(['status', 'scanned_at'], 'secretariat_attachment_scans_status_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_attachment_scans');
    }
};
