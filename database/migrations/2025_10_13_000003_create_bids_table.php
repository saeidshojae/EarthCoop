<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('auction_id')->constrained('auctions')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->bigInteger('shares_count');
            $table->decimal('bid_price', 20, 2);
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('bids');
    }
};
