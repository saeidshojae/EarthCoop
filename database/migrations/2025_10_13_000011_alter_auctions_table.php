<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('auctions', function (Blueprint $table) {
            if (!Schema::hasColumn('auctions', 'type')) {
                $table->string('type')->default('single_winner')->after('status');
            }
            if (!Schema::hasColumn('auctions', 'min_bid')) {
                $table->decimal('min_bid', 20, 2)->nullable()->after('type');
            }
            if (!Schema::hasColumn('auctions', 'max_bid')) {
                $table->decimal('max_bid', 20, 2)->nullable()->after('min_bid');
            }
            if (!Schema::hasColumn('auctions', 'lot_size')) {
                $table->bigInteger('lot_size')->default(1)->after('max_bid');
            }
            if (!Schema::hasColumn('auctions', 'channel_id')) {
                $table->unsignedBigInteger('channel_id')->nullable()->after('lot_size');
            }
            if (!Schema::hasColumn('auctions', 'ends_at')) {
                $table->timestamp('ends_at')->nullable()->after('end_time');
            }
        });
    }
    
    public function down() {
        Schema::table('auctions', function (Blueprint $table) {
            $table->dropColumn(['type', 'min_bid', 'max_bid', 'lot_size', 'channel_id', 'ends_at']);
        });
    }
};
