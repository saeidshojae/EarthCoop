<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_point_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_point_transaction_id')->constrained('user_point_transactions')->cascadeOnDelete();
            $table->unsignedInteger('points_consumed');
            $table->string('conversion_key', 191);
            $table->unsignedBigInteger('policy_version_id')->nullable();
            $table->string('policy_version')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'user_point_transaction_id']);
            $table->index('conversion_key');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_point_consumptions');
    }
};
