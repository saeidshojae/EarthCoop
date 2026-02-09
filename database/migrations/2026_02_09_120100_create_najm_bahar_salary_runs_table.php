<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('najm_bahar_salary_runs')) {
            return;
        }

        Schema::create('najm_bahar_salary_runs', function (Blueprint $table) {
            $table->id();
            $table->date('run_date');
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status')->default('pending'); // pending|processed|failed
            $table->unsignedBigInteger('created_by')->nullable()->index();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_salary_runs');
    }
};
