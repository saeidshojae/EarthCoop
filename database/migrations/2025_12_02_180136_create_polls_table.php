<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('polls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->foreignId('created_by')->constrained('users')->onDelete('cascade');
            $table->foreignId('skill_id')->nullable()->constrained('experience_fields')->onDelete('set null');
            $table->string('question');
            $table->boolean('is_multiple')->default(false);
            $table->boolean('is_anonymous')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('show_results')->default(true);
            $table->integer('type')->default(0);
            $table->integer('main_type')->default(0);
            $table->longText('read_by')->nullable()->comment('JSON array of user_id => timestamp');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('group_id');
            $table->index('created_by');
            $table->index('expires_at');
        });

        Schema::create('poll_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('polls')->onDelete('cascade');
            $table->string('text');
            $table->index('poll_id');
        });

        Schema::create('poll_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('poll_id')->constrained('polls')->onDelete('cascade');
            $table->foreignId('option_id')->constrained('poll_options')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->timestamps();

            $table->index('poll_id');
            $table->index('option_id');
            $table->index('user_id');
            $table->unique(['poll_id', 'user_id', 'option_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('poll_votes');
        Schema::dropIfExists('poll_options');
        Schema::dropIfExists('polls');
    }
};