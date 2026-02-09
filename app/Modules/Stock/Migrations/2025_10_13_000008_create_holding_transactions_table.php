<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('holding_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('holding_id');
            $table->foreign('holding_id')->references('id')->on('holdings')->onDelete('cascade');
            $table->enum('type', ['credit', 'debit', 'settlement', 'trade']);
            $table->bigInteger('quantity');
            $table->string('ref_type')->nullable(); // polymorphic type
            $table->unsignedBigInteger('ref_id')->nullable(); // polymorphic id
            $table->json('meta')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index(['ref_type', 'ref_id']);
            $table->index(['type', 'created_at']);
        });
    }
    
    public function down() {
        Schema::dropIfExists('holding_transactions');
    }
};
