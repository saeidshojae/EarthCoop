<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_bahar_projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('group_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('occupational_field_id')->constrained('occupational_fields')->restrictOnDelete();
            $table->string('title');
            $table->string('project_type', 20);
            $table->decimal('profit_percent', 5, 2)->default(0);
            $table->text('summary');
            $table->string('full_plan_path');
            $table->string('full_plan_original_name')->nullable();
            $table->string('full_plan_mime', 120)->nullable();
            $table->unsignedInteger('full_plan_size')->nullable();
            $table->unsignedBigInteger('investment_amount');
            $table->unsignedInteger('duration_months');
            $table->string('status', 20)->default('pending');
            $table->text('rejection_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('archived_at')->nullable();
            $table->timestamp('resubmitted_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['user_id', 'status']);
            $table->index(['group_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_projects');
    }
};
