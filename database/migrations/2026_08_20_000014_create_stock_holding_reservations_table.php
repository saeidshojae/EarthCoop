<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_holding_reservations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('holding_id')->constrained('holdings')->restrictOnDelete();
            $table->unsignedBigInteger('seller_user_id')->index();
            $table->unsignedBigInteger('auction_id')->index();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('settled_quantity')->default(0);
            $table->unsignedInteger('released_quantity')->default(0);
            $table->string('status', 24)->default('reserved')->index();
            $table->string('reservation_key', 191)->unique();
            $table->json('metadata')->nullable();
            $table->timestamp('reserved_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamp('settled_at')->nullable();
            $table->timestamps();
            $table->index(['holding_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_holding_reservations');
    }
};
