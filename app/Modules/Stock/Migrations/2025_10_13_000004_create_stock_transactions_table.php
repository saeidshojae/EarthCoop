<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('auction_id')->nullable()->constrained('auctions')->onDelete('set null');
            $table->bigInteger('shares_count');
            $table->decimal('price', 20, 2);
            $table->string('type'); // buy/sell
            $table->text('info')->nullable();
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('stock_transactions');
    }
};
