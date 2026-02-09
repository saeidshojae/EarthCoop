<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasTable('holding_transactions')) {
            Schema::create('holding_transactions', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('holding_id');
                $table->string('type');
                $table->bigInteger('quantity');
                $table->string('ref_type')->nullable();
                $table->unsignedBigInteger('ref_id')->nullable();
                $table->json('meta')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();
                
                $table->index(['ref_type', 'ref_id']);
                $table->index(['type', 'created_at']);
            });
        }
    }
    
    public function down() {
        Schema::dropIfExists('holding_transactions');
    }
};
