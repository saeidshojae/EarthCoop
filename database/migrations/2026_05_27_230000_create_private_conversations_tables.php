<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('private_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('private_conversation_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_conversation_id')->constrained('private_conversations')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();
            $table->unique(['private_conversation_id', 'user_id'], 'pcu_conversation_user_unique');
        });

        Schema::create('private_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('private_conversation_id')->constrained('private_conversations')->onDelete('cascade');
            $table->foreignId('sender_id')->constrained('users')->onDelete('cascade');
            $table->text('message');
            $table->timestamps();
            $table->index(['private_conversation_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('private_messages');
        Schema::dropIfExists('private_conversation_user');
        Schema::dropIfExists('private_conversations');
    }
};
