<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('wallet_id');
            $table->foreign('wallet_id')->references('id')->on('wallets')->onDelete('cascade');
            $table->enum('type', ['credit', 'debit', 'hold', 'release', 'settlement']);
            $table->decimal('amount', 20, 2);
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
        Schema::dropIfExists('wallet_transactions');
    }
};
