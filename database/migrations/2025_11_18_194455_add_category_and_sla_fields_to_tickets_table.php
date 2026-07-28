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
        Schema::table('tickets', function (Blueprint $table) {
            $table->string('category')->nullable()->after('priority');
            $table->timestamp('first_response_at')->nullable()->after('created_at');
            $table->timestamp('resolved_at')->nullable()->after('first_response_at');
            $table->timestamp('sla_deadline')->nullable()->after('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('tickets', function (Blueprint $table) {
            $table->dropColumn(['category', 'first_response_at', 'resolved_at', 'sla_deadline']);
        });
    }
};
