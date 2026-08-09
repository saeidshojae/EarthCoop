<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_bahar_treasury_transfers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_fund_id')->constrained('najm_bahar_treasury_funds')->restrictOnDelete();
            $table->foreignId('to_fund_id')->constrained('najm_bahar_treasury_funds')->restrictOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained('najm_transactions')->nullOnDelete();
            $table->foreignId('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedBigInteger('amount');
            $table->string('reason', 500);
            $table->string('policy_reference')->nullable();
            $table->string('idempotency_key')->unique();
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_treasury_transfers');
    }
};
