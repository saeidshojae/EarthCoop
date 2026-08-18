<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_correspondence_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->unique()->constrained('secretariat_records')->cascadeOnDelete();
            $table->string('external_reference_number', 255)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('channel', 24)->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('external_reference_number', 'secretariat_corr_external_ref_idx');
            $table->index('received_at', 'secretariat_corr_received_at_idx');
            $table->index('sent_at', 'secretariat_corr_sent_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_correspondence_details');
    }
};
