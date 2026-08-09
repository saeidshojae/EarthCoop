<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_proposal_referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('governance_proposals')->cascadeOnDelete();
            $table->foreignId('agenda_item_id')->constrained('governance_agenda_items')->cascadeOnDelete();
            $table->foreignId('source_group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('target_group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('referred_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 30)->default('pending');
            $table->text('request_notes')->nullable();
            $table->text('response_notes')->nullable();
            $table->json('assessment')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['target_group_id', 'status']);
            $table->index(['proposal_id', 'status']);
        });

        Schema::create('governance_eligibility_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('poll_id')->nullable()->constrained('polls')->nullOnDelete();
            $table->foreignId('captured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('purpose', 40)->default('resolution_vote');
            $table->string('status', 20)->default('capturing');
            $table->unsignedBigInteger('eligible_count')->default(0);
            $table->unsignedInteger('chunk_size')->default(1000);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->json('criteria');
            $table->string('membership_fingerprint', 64)->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'captured_at']);
            $table->index(['poll_id', 'status']);
        });

        Schema::create('governance_eligibility_snapshot_chunks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('snapshot_id')->constrained('governance_eligibility_snapshots')->cascadeOnDelete();
            $table->unsignedInteger('chunk_index');
            $table->unsignedInteger('member_count');
            $table->json('member_ids');
            $table->timestamps();

            $table->unique(['snapshot_id', 'chunk_index'], 'gov_elig_snapshot_chunk_unique');
        });

        Schema::table('governance_resolutions', function (Blueprint $table) {
            $table->foreignId('eligibility_snapshot_id')
                ->nullable()
                ->after('poll_id')
                ->constrained('governance_eligibility_snapshots')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('governance_resolutions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('eligibility_snapshot_id');
        });
        Schema::dropIfExists('governance_eligibility_snapshot_chunks');
        Schema::dropIfExists('governance_eligibility_snapshots');
        Schema::dropIfExists('governance_proposal_referrals');
    }
};
