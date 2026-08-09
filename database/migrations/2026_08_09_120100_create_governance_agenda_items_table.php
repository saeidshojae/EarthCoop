<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_agenda_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('governance_proposals')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 40)->default('scheduled');
            $table->boolean('professional_referral_required')->default(false);
            $table->text('referral_notes')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'status']);
            $table->index(['proposal_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_agenda_items');
    }
};
