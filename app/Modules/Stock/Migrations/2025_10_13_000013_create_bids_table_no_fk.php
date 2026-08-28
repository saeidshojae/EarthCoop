<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        if (!Schema::hasTable('bids')) {
            Schema::create('bids', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('auction_id');
                $table->unsignedBigInteger('user_id');
                $table->decimal('price', 20, 2);
                $table->bigInteger('quantity');
                $table->string('status')->default('active');
                $table->timestamps();

                $table->index(['auction_id', 'price', 'created_at']);
                $table->index(['user_id', 'status']);
            });
        }
    }

    public function down() {
        // Historical no-FK fallback only. The canonical bids table is owned by
        // the base Stock migration and must survive this rollback.
    }
};
