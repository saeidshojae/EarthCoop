<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('message_reactions', function (Blueprint $table) {
            $table->id();
            
            // Polymorphic message relationship
            $table->morphs('message'); // message_id + message_type
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            // Reaction emoji (👍, ❤️, 😂, 😮, 😢, 🔥, 👎)
            $table->string('reaction_type', 10)->default('👍');
            
            $table->timestamps();
            
            // Prevent duplicate reactions: same user, same message, same reaction
            $table->unique(['message_id', 'message_type', 'user_id', 'reaction_type'], 'message_reaction_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('message_reactions');
    }
};