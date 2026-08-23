<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_process_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('subject_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('election_appointments')->nullOnDelete();
            $table->string('ground', 48);
            $table->string('challenged_event', 64);
            $table->unsignedBigInteger('challenged_event_id')->nullable();
            $table->dateTime('event_occurred_at');
            $table->text('statement')->nullable();
            $table->string('automatic_status', 32)->default('pending');
            $table->json('automatic_result')->nullable();
            $table->string('human_status', 32)->default('not_requested');
            $table->unsignedInteger('support_count')->default(0);
            $table->dateTime('human_deadline_at');
            $table->timestamp('human_requested_at')->nullable();
            $table->timestamp('decision_due_at')->nullable();
            $table->string('interim_state', 24)->default('none');
            $table->text('interim_reason')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decision', 24)->nullable();
            $table->text('decision_reason')->nullable();
            $table->string('remediation_reference', 255)->nullable();
            $table->timestamps();

            $table->index(['election_id', 'human_status', 'decision_due_at'], 'election_reviews_status_due_index');
            $table->index(['appointment_id', 'human_status'], 'election_reviews_appointment_index');
            $table->index(['challenged_event', 'challenged_event_id'], 'election_reviews_event_index');
        });

        Schema::create('election_process_review_endorsements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('election_process_reviews')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->dateTime('endorsed_at');
            $table->unique(['review_id', 'user_id'], 'election_review_endorsement_unique');
        });

        Schema::create('election_review_audit_accesses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('review_id')->constrained('election_process_reviews')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('authority_path', 64);
            $table->string('purpose', 160);
            $table->json('scope')->nullable();
            $table->dateTime('accessed_at');
            $table->timestamps();
            $table->index(['review_id', 'accessed_at'], 'election_review_audit_access_index');
        });

        Schema::table('election_appointments', function (Blueprint $table) {
            $table->string('review_state', 24)->default('clear')->after('status');
            $table->index(['review_state', 'status'], 'election_appointments_review_state_index');
        });
    }

    public function down(): void
    {
        Schema::table('election_appointments', function (Blueprint $table) {
            $table->dropIndex('election_appointments_review_state_index');
            $table->dropColumn('review_state');
        });
        Schema::dropIfExists('election_review_audit_accesses');
        Schema::dropIfExists('election_process_review_endorsements');
        Schema::dropIfExists('election_process_reviews');
    }
};
