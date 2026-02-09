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
        Schema::table('group_user', function (Blueprint $table) {
            if (!Schema::hasColumn('group_user', 'last_read_message_id')) {
                $table->unsignedBigInteger('last_read_message_id')->nullable()->after('expired');
                
                // Foreign key to messages table
                if (Schema::hasTable('messages')) {
                    $table->foreign('last_read_message_id')
                        ->references('id')
                        ->on('messages')
                        ->onDelete('set null');
                }
                
                // Index for better query performance
                $table->index('last_read_message_id');
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
        Schema::table('group_user', function (Blueprint $table) {
            if (Schema::hasColumn('group_user', 'last_read_message_id')) {
                $table->dropForeign(['last_read_message_id']);
                $table->dropIndex(['last_read_message_id']);
                $table->dropColumn('last_read_message_id');
            }
        });
    }
};
