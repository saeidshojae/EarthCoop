<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_hoda_group_attention_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->unique()->constrained('groups')->cascadeOnDelete();
            $table->boolean('enabled')->default(false);
            $table->unsignedSmallInteger('due_soon_hours')->default(48);
            $table->unsignedSmallInteger('suppress_minutes')->default(720);
            $table->boolean('alert_overdue')->default(true);
            $table->boolean('alert_due_soon')->default(true);
            $table->boolean('alert_blocked')->default(true);
            $table->boolean('alert_urgent')->default(true);
            $table->boolean('alert_unassigned')->default(true);
            $table->string('digest_mode', 20)->default('daily');
            $table->string('timezone', 64)->default('UTC');
            $table->string('preferred_time', 5)->default('08:00');
            $table->timestamp('last_evaluated_at')->nullable();
            $table->timestamp('last_digest_at')->nullable();
            $table->timestamps();
        });

        Schema::create('najm_hoda_group_attention_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('action_item_id')->constrained('najm_hoda_group_action_items')->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->string('fingerprint', 64)->unique();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'resolved_at']);
            $table->index(['event_type', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_hoda_group_attention_events');
        Schema::dropIfExists('najm_hoda_group_attention_settings');
    }
};
