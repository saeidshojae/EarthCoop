<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_point_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->integer('delta');
            $table->bigInteger('balance_after')->nullable();
            $table->string('action');
            $table->string('source')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->longText('metadata')->nullable();
            $table->boolean('is_cashed')->default(false);
            $table->timestamp('cashed_at')->nullable();
            $table->bigInteger('cashed_amount_gol')->default(0);
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_point_transactions');
    }
};