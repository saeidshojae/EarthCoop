<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_retention_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_id')->constrained('secretariat_records')->restrictOnDelete();
            $table->unsignedInteger('assignment_sequence');
            $table->enum('disposition', ['preserve', 'review', 'eligible_for_disposition'])->default('preserve');
            $table->timestamp('retention_until')->nullable();
            $table->string('policy_reference', 255)->nullable();
            $table->text('reason')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['record_id', 'assignment_sequence'], 'secretariat_retention_record_seq_uq');
            $table->index(['disposition', 'retention_until'], 'secretariat_retention_disposition_date_idx');
        });

        Schema::create('secretariat_legal_holds', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('record_id')->constrained('secretariat_records')->restrictOnDelete();
            $table->string('hold_reference', 255)->nullable();
            $table->text('reason');
            $table->json('metadata')->nullable();
            $table->foreignId('placed_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('placed_at')->useCurrent();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->timestamps();

            $table->index(['record_id', 'released_at'], 'secretariat_legal_hold_active_idx');
            $table->index('hold_reference', 'secretariat_legal_hold_reference_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_legal_holds');
        Schema::dropIfExists('secretariat_retention_assignments');
    }
};
