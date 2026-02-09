<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('najm_bahar_salary_rules')) {
            return;
        }

        Schema::create('najm_bahar_salary_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('rule_type'); // role|user|project
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->tinyInteger('role_code')->nullable(); // 2=inspector, 3=manager
            $table->string('project_id')->nullable();
            $table->bigInteger('amount_gol');
            $table->string('schedule_type'); // monthly|interval|one_time
            $table->integer('interval_days')->nullable();
            $table->timestamp('start_at')->nullable();
            $table->timestamp('end_at')->nullable();
            $table->integer('min_activity_score')->default(0);
            $table->boolean('requires_senior_approval')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_run_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_salary_rules');
    }
};
