<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('founder_email_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->nullable()->constrained('email_templates')->nullOnDelete();
            $table->json('recipients');
            $table->string('subject');
            $table->longText('body');
            $table->json('variables')->nullable();
            $table->string('status', 32)->default('draft')->index();
            $table->string('reason_code', 64)->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('founder_email_drafts');
    }
};
