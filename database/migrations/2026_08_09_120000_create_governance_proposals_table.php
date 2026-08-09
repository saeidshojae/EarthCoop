<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_proposals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 50)->default('general');
            $table->string('title');
            $table->text('summary')->nullable();
            $table->longText('description')->nullable();
            $table->string('status', 40)->default('draft');
            $table->unsignedBigInteger('support_count')->default(0);
            $table->unsignedBigInteger('support_threshold')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('supported_at')->nullable();
            $table->timestamp('agenda_at')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'status']);
            $table->index(['group_id', 'type']);
        });

        Schema::create('governance_proposal_supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('governance_proposals')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source', 40)->default('explicit_endorsement');
            $table->string('source_reference')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['proposal_id', 'user_id']);
            $table->index(['proposal_id', 'source']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_proposal_supports');
        Schema::dropIfExists('governance_proposals');
    }
};
