<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('trigger_type');
            $table->string('event_key')->nullable();
            $table->json('condition_definition')->nullable();
            $table->json('audience_definition');
            $table->foreignId('communication_template_id')
                ->constrained('communication_templates')
                ->restrictOnDelete();
            $table->foreignId('communication_sender_identity_id')
                ->nullable()
                ->constrained('communication_sender_identities')
                ->restrictOnDelete();
            $table->string('classification');
            $table->unsignedSmallInteger('priority')->default(2);
            $table->unsignedBigInteger('delay_seconds')->default(0);
            $table->boolean('is_active')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['trigger_type', 'event_key', 'is_active']);
        });

        Schema::create('communication_rule_schedules', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_rule_id')
                ->unique()
                ->constrained('communication_rules')
                ->cascadeOnDelete();
            $table->string('frequency');
            $table->json('schedule_definition');
            $table->string('timezone')->default('Asia/Tehran');
            $table->string('timezone_mode')->default('system');
            $table->timestamp('next_run_at')->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index('next_run_at');
        });

        Schema::create('communication_campaigns', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('status')->default('draft');
            $table->json('audience_definition');
            $table->foreignId('communication_template_id')
                ->constrained('communication_templates')
                ->restrictOnDelete();
            $table->foreignId('communication_sender_identity_id')
                ->nullable()
                ->constrained('communication_sender_identities')
                ->restrictOnDelete();
            $table->string('classification');
            $table->unsignedSmallInteger('priority')->default(4);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamp('paused_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'scheduled_at']);
        });

        Schema::create('communication_runs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_rule_id')
                ->nullable()
                ->constrained('communication_rules')
                ->nullOnDelete();
            $table->foreignId('communication_campaign_id')
                ->nullable()
                ->constrained('communication_campaigns')
                ->nullOnDelete();
            $table->string('run_key')->nullable()->unique();
            $table->string('status')->default('pending');
            $table->unsignedBigInteger('matched_count')->default(0);
            $table->unsignedBigInteger('eligible_count')->default(0);
            $table->unsignedBigInteger('suppressed_count')->default(0);
            $table->unsignedBigInteger('invalid_count')->default(0);
            $table->unsignedBigInteger('queued_count')->default(0);
            $table->unsignedBigInteger('sent_count')->default(0);
            $table->unsignedBigInteger('failed_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_runs');
        Schema::dropIfExists('communication_campaigns');
        Schema::dropIfExists('communication_rule_schedules');
        Schema::dropIfExists('communication_rules');
    }
};
