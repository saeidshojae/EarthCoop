<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('founder_email_template_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->json('changes');
            $table->string('status', 32)->default('draft')->index();
            $table->string('reason_code', 100)->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('founder_email_template_drafts');
    }
};
