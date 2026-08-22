<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_vote_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
            $table->foreignId('ballot_event_id')->unique()->constrained('election_ballot_events')->cascadeOnDelete();
            $table->foreignId('author_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 32);
            $table->string('visibility', 32);
            $table->boolean('anonymous')->default(false);
            $table->text('body');
            $table->string('moderation_status', 32)->default('pending_review');
            $table->json('moderation_reasons')->nullable();
            $table->string('moderation_source', 64)->default('legacy_backfill');
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->date('public_bucket_start')->nullable();
            $table->timestamps();

            $table->index(['election_id', 'subject_user_id', 'moderation_status'], 'election_feedback_subject_status_idx');
            $table->index(['election_id', 'visibility', 'moderation_status'], 'election_feedback_visibility_status_idx');
            $table->index(['election_id', 'public_bucket_start'], 'election_feedback_public_bucket_idx');
        });

        // Existing comments become independent feedback records, but are deliberately
        // not auto-published. They require review because they predate the moderation gate.
        DB::table('election_ballot_events')
            ->whereNotNull('comment')
            ->where('comment', '<>', '')
            ->orderBy('id')
            ->chunkById(200, function ($events): void {
                foreach ($events as $event) {
                    $subjectId = $event->candidate_user_id ?: $event->previous_candidate_user_id;
                    DB::table('election_vote_feedback')->insertOrIgnore([
                        'election_id' => $event->election_id,
                        'ballot_event_id' => $event->id,
                        'author_user_id' => $event->voter_id,
                        'subject_user_id' => $subjectId,
                        'event_type' => $event->event_type,
                        'visibility' => $event->comment_visibility ?: 'subject_only',
                        'anonymous' => (bool) ($event->comment_anonymous ?? false),
                        'body' => $event->comment,
                        'moderation_status' => 'pending_review',
                        'moderation_reasons' => json_encode(['legacy_unmoderated']),
                        'moderation_source' => 'legacy_backfill',
                        'moderated_at' => null,
                        'published_at' => null,
                        'public_bucket_start' => null,
                        'created_at' => $event->created_at ?? now(),
                        'updated_at' => now(),
                    ]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::dropIfExists('election_vote_feedback');
    }
};
