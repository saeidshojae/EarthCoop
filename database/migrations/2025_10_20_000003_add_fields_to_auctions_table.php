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
            // Indexes if missing
            $table->index(['status', 'ends_at']);
            $table->index(['type', 'status']);
        });
    }

    public function down() {
        Schema::table('auctions', function (Blueprint $table) {
            // Down not fully reversible without doctrine/dbal; keep minimal
            if (Schema::hasColumn('auctions', 'type')) $table->dropColumn('type');
            if (Schema::hasColumn('auctions', 'min_bid')) $table->dropColumn('min_bid');
            if (Schema::hasColumn('auctions', 'max_bid')) $table->dropColumn('max_bid');
            if (Schema::hasColumn('auctions', 'lot_size')) $table->dropColumn('lot_size');
            if (Schema::hasColumn('auctions', 'channel_id')) $table->dropColumn('channel_id');
        });
    }
};
