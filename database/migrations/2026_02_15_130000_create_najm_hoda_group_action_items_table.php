<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_hoda_group_action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('source_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('response_message_id')->nullable()->constrained('messages')->nullOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title', 255);
            $table->text('details')->nullable();
            $table->string('assignee_name', 150)->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('due_text', 100)->nullable();
            $table->string('priority', 20)->default('medium');
            $table->string('status', 20)->default('open');
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['group_id', 'status']);
            $table->index(['group_id', 'due_at']);
            $table->index(['priority', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_hoda_group_action_items');
    }
};
