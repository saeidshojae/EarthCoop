<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_chat_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_conversation_id')->constrained('private_conversations')->onDelete('cascade');
            $table->foreignId('reported_message_id')->constrained('private_messages')->onDelete('cascade');
            $table->foreignId('reporter_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('reported_user_id')->constrained('users')->onDelete('cascade');
            $table->enum('reason', ['spam', 'harassment', 'inappropriate_content', 'abuse', 'other'])->default('inappropriate_content');
            $table->text('description')->nullable();
            $table->enum('status', ['pending', 'reviewed', 'resolved', 'dismissed'])->default('pending');
            $table->text('admin_notes')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
            
            $table->index(['private_conversation_id', 'status']);
            $table->index(['reported_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_chat_reports');
    }
};