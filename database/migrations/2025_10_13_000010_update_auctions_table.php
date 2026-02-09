<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('auctions', function (Blueprint $table) {
            $table->enum('type', ['single_winner', 'uniform_price', 'pay_as_bid'])->default('single_winner')->after('status');
            $table->decimal('min_bid', 20, 2)->nullable()->after('type');
            $table->decimal('max_bid', 20, 2)->nullable()->after('min_bid');
            $table->bigInteger('lot_size')->default(1)->after('max_bid');
            $table->unsignedBigInteger('channel_id')->nullable()->after('lot_size');
            $table->timestamp('ends_at')->nullable()->after('end_time');
            $table->enum('status', ['scheduled', 'running', 'settling', 'settled', 'canceled'])->default('scheduled')->change();
            
            $table->index(['status', 'ends_at']);
            $table->index(['type', 'status']);
        });
    }
    
    public function down() {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['type', 'min_bid', 'max_bid', 'lot_size', 'channel_id', 'ends_at']);
            $table->enum('status', ['active', 'inactive'])->default('active')->change();
            $table->dropIndex(['status', 'ends_at']);
            $table->dropIndex(['type', 'status']);
        });
    }
};
