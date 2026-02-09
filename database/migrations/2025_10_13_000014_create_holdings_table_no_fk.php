<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasTable('holdings')) {
            Schema::create('holdings', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->unsignedBigInteger('stock_id');
                $table->bigInteger('quantity')->default(0);
                $table->timestamps();
                
                $table->unique(['user_id', 'stock_id']);
            });
        }
    }
    
    public function down() {
        Schema::dropIfExists('holdings');
    }
};
