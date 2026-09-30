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
            $table->foreignId('communication_rule_id')->nullable()->constrained('communication_rules')->nullOnDelete();
            $table->foreignId('communication_campaign_id')->nullable()->constrained('communication_campaigns')->nullOnDelete();
            $table->foreignId('communication_run_id')->nullable()->constrained('communication_runs')->nullOnDelete();
            $table->foreignId('communication_template_version_id')
                ->constrained('communication_template_versions')
                ->restrictOnDelete();
            $table->string('classification');
            $table->unsignedSmallInteger('priority')->default(2);
            $table->json('context_snapshot')->nullable();
            $table->string('status')->default('pending');
            $table->string('deduplication_key')->nullable()->unique();
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('communication_recipients', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_id')->constrained('communications')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('email');
            $table->string('locale', 16)->default('fa');
            $table->foreignId('communication_template_version_id')
                ->constrained('communication_template_versions')
                ->restrictOnDelete();
            $table->json('preference_decision')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['communication_id', 'status']);
            $table->index(['user_id', 'status']);
            $table->index('email');
        });

        Schema::create('communication_delivery_attempts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_recipient_id')
                ->constrained('communication_recipients')
                ->restrictOnDelete();
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

            $table->unique(
                ['communication_recipient_id', 'attempt_number'],
                'communication_delivery_attempt_number_unique'
            );
            $table->index(['status', 'failure_class']);
        });

        Schema::create('communication_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('topic_key');
            $table->string('channel')->default('email');
            $table->string('preference');
            $table->string('frequency')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'topic_key', 'channel'],
                'communication_preferences_user_topic_channel_unique'
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
