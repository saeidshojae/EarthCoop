<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('stocks')->onDelete('cascade');
            $table->bigInteger('shares_count');
            $table->decimal('base_price', 20, 2);
            $table->timestamp('start_time')->useCurrent();
            $table->timestamp('end_time')->nullable();
            $table->string('status')->default('active');
            $table->text('info')->nullable();
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('auctions');
    }
};
