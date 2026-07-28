<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up() {
        Schema::table('stocks', function (Blueprint $table) {
            $table->bigInteger('available_shares')->default(0)->after('total_shares');
        });

        // initialize available_shares = total_shares for existing records
        if (Schema::hasTable('stocks')) {
            \DB::table('stocks')->whereNull('available_shares')->orWhere('available_shares', 0)->update(['available_shares' => \DB::raw('total_shares')]);
        }
    }

    public function down() {
        Schema::table('stocks', function (Blueprint $table) {
            $table->dropColumn('available_shares');
        });
    }
};
