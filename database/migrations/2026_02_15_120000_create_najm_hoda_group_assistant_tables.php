<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_hoda_group_configs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->boolean('enabled')->default(true);
            $table->string('assistant_role')->default('secretary');
            $table->string('default_agent')->default('steward');
            $table->string('auto_reply_mode')->default('mention_or_question');
            $table->string('knowledge_scope')->default('hybrid');
            $table->boolean('meeting_mode_enabled')->default(true);
            $table->boolean('allow_proactive_guidance')->default(true);
            $table->unsignedInteger('max_replies_per_hour')->default(12);
            $table->unsignedInteger('min_reply_interval_seconds')->default(90);
            $table->json('policy')->nullable();
            $table->timestamps();

            $table->unique('group_id');
            $table->index(['enabled', 'auto_reply_mode']);
            $table->index('knowledge_scope');
        });

        Schema::create('najm_hoda_group_memories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->string('memory_type', 40)->default('summary');
            $table->string('scope', 20)->default('group');
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamp('captured_at')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'memory_type']);
            $table->index(['scope', 'created_at']);
        });

        Schema::create('najm_hoda_group_action_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('trigger_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('response_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->string('action_type', 30);
            $table->string('decision', 30)->default('skipped');
            $table->string('agent', 30)->nullable();
            $table->text('reason')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'created_at']);
            $table->index(['action_type', 'decision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_hoda_group_action_logs');
        Schema::dropIfExists('najm_hoda_group_memories');
        Schema::dropIfExists('najm_hoda_group_configs');
    }
};
