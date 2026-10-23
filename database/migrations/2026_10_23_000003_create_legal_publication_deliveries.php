<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_publication_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_version_id')->constrained('legal_document_versions')->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->string('channel', 20);
            $table->string('status', 20)->default('queued');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['legal_document_version_id', 'user_id', 'channel'], 'legal_publication_delivery_once');
            $table->index(['legal_document_version_id', 'channel', 'status'], 'legal_publication_delivery_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_publication_deliveries');
    }
};
