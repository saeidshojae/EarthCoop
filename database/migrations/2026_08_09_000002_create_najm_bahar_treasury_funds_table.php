<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_bahar_treasury_funds', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('account_id')->constrained('najm_accounts')->cascadeOnUpdate()->restrictOnDelete();
            $table->text('purpose')->nullable();
            $table->unsignedBigInteger('required_reserve')->default(0);
            $table->unsignedBigInteger('committed_liabilities')->default(0);
            $table->boolean('is_active')->default(true);
            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_treasury_funds');
    }
};
