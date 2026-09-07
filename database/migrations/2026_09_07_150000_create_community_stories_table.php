<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_stories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->string('display_name')->nullable();
            $table->boolean('show_name')->default(true);
            $table->string('avatar_path')->nullable();
            $table->boolean('show_avatar')->default(false);
            $table->string('role')->nullable();
            $table->string('location')->nullable();
            $table->string('locale', 10)->default('fa');
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('consent_publication_at')->nullable()->index();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->boolean('is_featured')->default(false)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_featured', 'published_at'], 'community_stories_welcome_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_stories');
    }
};
