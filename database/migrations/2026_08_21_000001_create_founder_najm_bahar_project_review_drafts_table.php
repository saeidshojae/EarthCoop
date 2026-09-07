<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('founder_najm_bahar_project_review_drafts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('project_id');
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->string('status', 32)->default('draft');
            $table->text('summary')->nullable();
            $table->json('findings')->nullable();
            $table->string('reason_code', 120)->nullable();
            $table->timestamps();

            $table->foreign('project_id', 'fnb_project_review_project_fk')
                ->references('id')->on('najm_bahar_projects')->cascadeOnDelete();
            $table->foreign('requested_by_user_id', 'fnb_project_review_requester_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->index(['project_id', 'status'], 'fnb_project_review_project_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('founder_najm_bahar_project_review_drafts');
    }
};
