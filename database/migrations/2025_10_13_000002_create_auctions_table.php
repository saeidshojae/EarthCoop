<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auctions', function (Blueprint $table) {

            $table->id();

            $table->foreignId('stock_id')
                ->constrained('stocks')
                ->cascadeOnDelete();

            $table->bigInteger('shares_count');

            $table->decimal('base_price', 20, 2);

            $table->timestamp('start_time')->useCurrent();

            $table->timestamp('end_time')->nullable();

            $table->timestamp('ends_at')->nullable();

            $table->enum('status', [
                'scheduled',
                'running',
                'settling',
                'settled',
                'canceled',
            ])->default('scheduled');

            $table->enum('type', [
                'single_winner',
                'uniform_price',
                'pay_as_bid',
            ])->default('single_winner');

            $table->decimal('min_bid', 20, 2)->nullable();

            $table->decimal('max_bid', 20, 2)->nullable();

            $table->bigInteger('lot_size')->default(1);

            $table->unsignedBigInteger('channel_id')->nullable();

            $table->text('info')->nullable();

            $table->timestamps();

            $table->index(['status', 'ends_at']);
            $table->index(['type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auctions');
    }
};