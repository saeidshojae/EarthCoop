<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_active_bahar_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payer_account_id')->constrained('najm_accounts')->restrictOnDelete();
            $table->foreignId('payee_account_id')->nullable()->constrained('najm_accounts')->restrictOnDelete();
            $table->bigInteger('amount'); // integer Gol
            $table->bigInteger('settled_amount')->default(0);
            $table->bigInteger('refunded_amount')->default(0);
            $table->string('status', 24)->default('reserved');
            $table->string('reference_type', 80);
            $table->string('reference_id', 120);
            $table->string('reservation_key', 191)->unique();
            $table->string('settlement_key', 191)->nullable()->unique();
            $table->string('release_key', 191)->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['payer_account_id', 'status']);
            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_active_bahar_reservations');
    }
};
