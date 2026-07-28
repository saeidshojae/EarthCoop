<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('auctions', function (Blueprint $table) {
            if (!Schema::hasColumn('auctions', 'ends_at')) {
                $table->timestamp('ends_at')->nullable()->after('end_time');
            }
        });
    }

    public function down() {
        Schema::table('auctions', function (Blueprint $table) {
            if (Schema::hasColumn('auctions', 'ends_at')) {
                // Note: Dropping columns may require doctrine/dbal; down() left minimal.
                $table->dropColumn('ends_at');
            }
        });
    }
};
