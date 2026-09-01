<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 000003 already creates the legacy bids table. This migration is the
        // normalization boundary to the canonical Bid model vocabulary rather
        // than a second attempt to create the same table.
        if (! Schema::hasTable('bids')) {
            Schema::create('bids', function (Blueprint $table) {
                $table->id();
                $table->foreignId('auction_id')->constrained('auctions')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->decimal('price', 20, 2);
                $table->bigInteger('quantity');
                $table->string('status')->default('active');
                $table->timestamps();
                $table->index(['auction_id', 'price', 'created_at'], 'bids_auction_price_created');
                $table->index(['user_id', 'status'], 'bids_user_status');
            });

            return;
        }

        if (Schema::hasColumn('bids', 'bid_price') && ! Schema::hasColumn('bids', 'price')) {
            Schema::table('bids', function (Blueprint $table) {
                $table->renameColumn('bid_price', 'price');
            });
        }

        if (Schema::hasColumn('bids', 'shares_count') && ! Schema::hasColumn('bids', 'quantity')) {
            Schema::table('bids', function (Blueprint $table) {
                $table->renameColumn('shares_count', 'quantity');
            });
        }

        // Composite indexes use explicit names so the canonical query paths are
        // deterministic and later rollback does not depend on generated names.
        Schema::table('bids', function (Blueprint $table) {
            $table->index(['auction_id', 'price', 'created_at'], 'bids_auction_price_created');
            $table->index(['user_id', 'status'], 'bids_user_status');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('bids')) {
            return;
        }

        Schema::table('bids', function (Blueprint $table) {
            $table->dropIndex('bids_auction_price_created');
            $table->dropIndex('bids_user_status');
        });

        if (Schema::hasColumn('bids', 'price') && ! Schema::hasColumn('bids', 'bid_price')) {
            Schema::table('bids', function (Blueprint $table) {
                $table->renameColumn('price', 'bid_price');
            });
        }

        if (Schema::hasColumn('bids', 'quantity') && ! Schema::hasColumn('bids', 'shares_count')) {
            Schema::table('bids', function (Blueprint $table) {
                $table->renameColumn('quantity', 'shares_count');
            });
        }
    }
};
