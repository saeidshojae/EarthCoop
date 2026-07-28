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
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'documents')) {
                $table->text('documents')->nullable()->after('biografie');
            }
            if (!Schema::hasColumn('users', 'show_documents')) {
                $table->boolean('show_documents')->default(true)->after('documents');
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
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'documents')) {
                $table->dropColumn('documents');
            }
            if (Schema::hasColumn('users', 'show_documents')) {
                $table->dropColumn('show_documents');
            }
        });
    }
};
