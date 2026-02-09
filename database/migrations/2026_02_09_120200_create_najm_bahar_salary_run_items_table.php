<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('najm_bahar_salary_run_items')) {
            return;
        }

        Schema::create('najm_bahar_salary_run_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('run_id')->index();
            $table->unsignedBigInteger('rule_id')->index();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->index();
            $table->tinyInteger('role_code')->nullable();
            $table->string('project_id')->nullable();
            $table->date('period_start');
            $table->date('period_end');
            $table->bigInteger('amount_gol');
            $table->integer('activity_score')->nullable();
            $table->integer('activity_threshold')->nullable();
            $table->boolean('requires_senior_approval')->default(false);
            $table->timestamp('senior_approved_at')->nullable();
            $table->unsignedBigInteger('senior_approved_by')->nullable()->index();
            $table->string('status')->default('blocked'); // ready|blocked|paid|failed
            $table->string('blocked_reason')->nullable();
            $table->unsignedBigInteger('transaction_id')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_salary_run_items');
    }
};
