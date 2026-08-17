<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_hoda_group_meeting_minutes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_session_id')->unique()->constrained('group_sessions')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->enum('status', ['draft', 'approved'])->default('draft');
            $table->longText('summary')->nullable();
            $table->longText('minutes')->nullable();
            $table->json('evidence_snapshot')->nullable();
            $table->json('decision_candidates')->nullable();
            $table->json('action_candidates')->nullable();
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('generated_at')->nullable();
            $table->dateTime('approved_at')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_hoda_group_meeting_minutes');
    }
};
