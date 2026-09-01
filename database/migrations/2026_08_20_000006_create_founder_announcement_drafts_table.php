<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('founder_announcement_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->longText('content');
            $table->string('group_level', 64)->index();
            $table->string('image')->nullable();
            $table->boolean('should_pin')->default(false);
            $table->string('status', 32)->default('draft')->index();
            $table->string('reason_code', 64)->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('announcement_id')->nullable()->constrained('announcements')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('founder_announcement_drafts');
    }
};
