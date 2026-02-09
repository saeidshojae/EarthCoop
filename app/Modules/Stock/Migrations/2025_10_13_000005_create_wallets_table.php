<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->decimal('balance', 20, 2)->default(0);
            $table->decimal('held_amount', 20, 2)->default(0);
            $table->string('currency', 3)->default('IRR');
            $table->timestamps();
            
            $table->unique(['user_id', 'currency']);
        });
    }
    
    public function down() {
        Schema::dropIfExists('wallets');
    }
};
