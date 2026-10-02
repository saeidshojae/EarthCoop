<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communications', function (Blueprint $table): void {
            $table->id();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->foreignId('communication_rule_id')->nullable();
            $table->foreignId('communication_campaign_id')->nullable();
            $table->foreignId('communication_run_id')->nullable();
            $table->foreignId('communication_template_version_id');
            $table->string('classification');
            $table->unsignedSmallInteger('priority')->default(2);
            $table->json('context_snapshot')->nullable();
            $table->string('status')->default('pending');
            $table->string('deduplication_key')->nullable();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();

            $table->foreign('communication_rule_id', 'cc_message_rule_fk')
                ->references('id')->on('communication_rules')->nullOnDelete();
            $table->foreign('communication_campaign_id', 'cc_message_campaign_fk')
                ->references('id')->on('communication_campaigns')->nullOnDelete();
            $table->foreign('communication_run_id', 'cc_message_run_fk')
                ->references('id')->on('communication_runs')->nullOnDelete();
            $table->foreign('communication_template_version_id', 'cc_message_template_version_fk')
                ->references('id')->on('communication_template_versions')->restrictOnDelete();
            $table->unique('deduplication_key', 'cc_message_dedupe_uq');
            $table->index(['source_type', 'source_id'], 'cc_message_source_idx');
            $table->index(['status', 'scheduled_at'], 'cc_message_status_schedule_idx');
        });

        Schema::create('communication_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_id');
            $table->foreignId('user_id')->nullable();
            $table->string('email');
            $table->string('locale', 16)->default('fa');
            $table->foreignId('communication_template_version_id');
            $table->json('preference_decision')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('communication_id', 'cc_recipient_message_fk')
                ->references('id')->on('communications')->cascadeOnDelete();
            $table->foreign('user_id', 'cc_recipient_user_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('communication_template_version_id', 'cc_recipient_template_version_fk')
                ->references('id')->on('communication_template_versions')->restrictOnDelete();
            $table->index(['communication_id', 'status'], 'cc_recipient_message_status_idx');
            $table->index(['user_id', 'status'], 'cc_recipient_user_status_idx');
            $table->index('email', 'cc_recipient_email_idx');
        });

        Schema::create('communication_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_recipient_id');
            $table->unsignedInteger('attempt_number');
            $table->string('provider')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->string('status');
            $table->string('failure_class')->nullable();
            $table->string('failure_code')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->foreign('communication_recipient_id', 'cc_attempt_recipient_fk')
                ->references('id')->on('communication_recipients')->restrictOnDelete();
            $table->unique(
                ['communication_recipient_id', 'attempt_number'],
                'cc_attempt_recipient_number_uq'
            );
            $table->index(['status', 'failure_class'], 'cc_attempt_status_failure_idx');
        });

        Schema::create('communication_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->string('topic_key');
            $table->string('channel')->default('email');
            $table->string('preference');
            $table->string('frequency')->nullable();
            $table->timestamps();

            $table->foreign('user_id', 'cc_preference_user_fk')
                ->references('id')->on('users')->cascadeOnDelete();
            $table->unique(
                ['user_id', 'topic_key', 'channel'],
                'cc_preference_user_topic_channel_uq'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_preferences');
        Schema::dropIfExists('communication_delivery_attempts');
        Schema::dropIfExists('communication_recipients');
        Schema::dropIfExists('communications');
    }
};
