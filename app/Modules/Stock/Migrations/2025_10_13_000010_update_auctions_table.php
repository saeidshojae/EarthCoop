<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('auctions')) {
            return;
        }

        $canonicalColumns = ['type', 'min_bid', 'max_bid', 'lot_size', 'channel_id', 'ends_at'];
        $missing = array_values(array_filter(
            $canonicalColumns,
            fn (string $column): bool => ! Schema::hasColumn('auctions', $column)
        ));

        // The canonical database/migrations/000002 already contains the full
        // auction shape. This historical module migration now acts only as an
        // upgrade bridge for installations created from the older module schema.
        if ($missing !== []) {
            Schema::table('auctions', function (Blueprint $table) use ($missing) {
                if (in_array('type', $missing, true)) {
                    $table->enum('type', ['single_winner', 'uniform_price', 'pay_as_bid'])
                        ->default('single_winner')
                        ->after('status');
                }

                if (in_array('min_bid', $missing, true)) {
                    $table->decimal('min_bid', 20, 2)->nullable()->after('type');
                }

                if (in_array('max_bid', $missing, true)) {
                    $table->decimal('max_bid', 20, 2)->nullable()->after('min_bid');
                }

                if (in_array('lot_size', $missing, true)) {
                    $table->bigInteger('lot_size')->default(1)->after('max_bid');
                }

                if (in_array('channel_id', $missing, true)) {
                    $table->unsignedBigInteger('channel_id')->nullable()->after('lot_size');
                }

                if (in_array('ends_at', $missing, true)) {
                    $table->timestamp('ends_at')->nullable()->after('end_time');
                }
            });
        }

        // Legacy installations used a free-form/active status. Normalize the
        // vocabulary only when this migration actually bridged the legacy shape.
        if ($missing !== []) {
            Schema::table('auctions', function (Blueprint $table) {
                $table->enum('status', ['scheduled', 'running', 'settling', 'settled', 'canceled'])
                    ->default('scheduled')
                    ->change();
            });
        }

        if (! Schema::hasIndex('auctions', ['status', 'ends_at'])) {
            Schema::table('auctions', function (Blueprint $table) {
                $table->index(['status', 'ends_at']);
            });
        }

        if (! Schema::hasIndex('auctions', ['type', 'status'])) {
            Schema::table('auctions', function (Blueprint $table) {
                $table->index(['type', 'status']);
            });
        }
    }

    public function down(): void
    {
        // Intentionally non-destructive. These columns/indexes are part of the
        // canonical fresh Stock schema, so rolling back this historical upgrade
        // bridge must never remove canonical auction structure.
    }
};
