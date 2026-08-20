<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            // Canonical issuer boundary. Nullable keeps fresh installs aligned
            // with legacy rows: external settlement remains fail-closed until
            // the issuer is explicitly classified.
            $table->string('issuer_type', 32)->nullable();
            $table->unsignedBigInteger('issuer_id')->nullable();
            $table->decimal('startup_valuation', 20, 2);
            $table->bigInteger('total_shares');
            $table->decimal('base_share_price', 20, 2);
            $table->text('info')->nullable();
            $table->timestamps();

            $table->index(['issuer_type', 'issuer_id'], 'stocks_issuer_lookup');
        });
    }
    public function down() {
        Schema::dropIfExists('stocks');
    }
};
