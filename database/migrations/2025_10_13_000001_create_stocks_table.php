<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->decimal('startup_valuation', 20, 2);
            $table->bigInteger('total_shares');
            $table->decimal('base_share_price', 20, 2);
            $table->text('info')->nullable();
            $table->timestamps();
        });
    }
    public function down() {
        Schema::dropIfExists('stocks');
    }
};
