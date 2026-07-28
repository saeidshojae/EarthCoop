<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('messages', function (Blueprint $table) {
            if (!Schema::hasColumn('messages', 'file_path')) {
                $table->string('file_path')->nullable()->after('message');
            }
            if (!Schema::hasColumn('messages', 'file_type')) {
                $table->string('file_type')->nullable()->after('file_path');
            }
            if (!Schema::hasColumn('messages', 'file_name')) {
                $table->string('file_name')->nullable()->after('file_type');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('messages', function (Blueprint $table) {
            if (Schema::hasColumn('messages', 'file_path')) {
                $table->dropColumn('file_path');
            }
            if (Schema::hasColumn('messages', 'file_type')) {
                $table->dropColumn('file_type');
            }
            if (Schema::hasColumn('messages', 'file_name')) {
                $table->dropColumn('file_name');
            }
        });
    }
};
