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
        Schema::table('announcements', function (Blueprint $table) {
            $table->string('image')->nullable()->after('content');
            $table->boolean('should_pin')->default(true)->after('image');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->after('should_pin');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('announcements', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn(['image', 'should_pin', 'created_by']);
        });
    }
};
