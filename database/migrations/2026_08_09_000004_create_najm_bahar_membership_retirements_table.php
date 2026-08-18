<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('najm_bahar_membership_retirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 40);
            $table->unsignedBigInteger('constitutional_amount');
            $table->unsignedBigInteger('dim_cancelled')->default(0);
            $table->unsignedBigInteger('active_destroyed_from_burn_fund')->default(0);
            $table->unsignedBigInteger('active_destroyed_from_idle_tax_fund')->default(0);
            $table->unsignedBigInteger('outstanding_liability')->default(0);
            $table->string('status', 30)->default('completed');
            $table->string('idempotency_key')->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('retired_at');
            $table->timestamps();
        });

        Schema::create('najm_bahar_monetary_retirement_liabilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('retirement_id')->constrained('najm_bahar_membership_retirements')->cascadeOnDelete();
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('settled_amount')->default(0);
            $table->string('status', 30)->default('outstanding');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_monetary_retirement_liabilities');
        Schema::dropIfExists('najm_bahar_membership_retirements');
    }
};
