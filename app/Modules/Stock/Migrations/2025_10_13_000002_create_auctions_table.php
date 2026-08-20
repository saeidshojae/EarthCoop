<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('auctions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_id')->constrained('stocks')->onDelete('cascade');
            // Canonical market/settlement boundary. Legacy-compatible nullable
            // classification prevents old auctions from silently entering a
            // settlement path before they are explicitly classified.
            $table->string('market_type', 16)->nullable();
            $table->string('supply_source', 16)->nullable();
            $table->string('settlement_channel', 32)->nullable();
            $table->string('quote_unit', 16)->default('bahar');
            $table->bigInteger('shares_count');
            $table->decimal('base_price', 20, 2);
            $table->timestamp('start_time')->useCurrent();
            $table->timestamp('end_time')->nullable();
            $table->string('status')->default('active');
            $table->text('info')->nullable();
            $table->timestamps();

            $table->index(
                ['market_type', 'supply_source', 'settlement_channel'],
                'auctions_settlement_boundary'
            );
        });
    }
    public function down() {
        Schema::dropIfExists('auctions');
    }
};
