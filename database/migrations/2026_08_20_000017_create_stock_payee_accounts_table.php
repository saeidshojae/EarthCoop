<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_payee_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->unique()->constrained('stocks')->restrictOnDelete();
            $table->foreignId('account_id')->constrained('najm_accounts')->restrictOnDelete();
            $table->string('purpose', 40)->default('primary_capital');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('configured_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['purpose','is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_payee_accounts');
    }
};
