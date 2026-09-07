<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_dispatches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('secretariat_records')->cascadeOnDelete();
            $table->string('dispatch_type', 24);
            $table->string('status', 24)->default('pending');
            $table->string('channel', 24)->default('internal');
            $table->foreignId('target_party_id')->nullable()->constrained('secretariat_parties')->nullOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('instructions')->nullable();
            $table->string('external_reference_number', 255)->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['record_id', 'status'], 'secretariat_dispatches_record_status_idx');
            $table->index(['target_user_id', 'status'], 'secretariat_dispatches_user_status_idx');
            $table->index(['target_party_id', 'status'], 'secretariat_dispatches_party_status_idx');
            $table->index('dispatched_at', 'secretariat_dispatches_dispatched_at_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_dispatches');
    }
};
