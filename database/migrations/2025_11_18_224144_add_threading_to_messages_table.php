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
            if (!Schema::hasColumn('messages', 'thread_id')) {
                $table->foreignId('thread_id')->nullable()->after('parent_id')->constrained('messages')->onDelete('cascade')->comment('Root message of the thread');
            }
            if (!Schema::hasColumn('messages', 'reply_count')) {
                $table->integer('reply_count')->default(0)->after('thread_id')->comment('Number of replies in thread');
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
            if (Schema::hasColumn('messages', 'thread_id')) {
                $table->dropForeign(['thread_id']);
                $table->dropColumn('thread_id');
            }
            if (Schema::hasColumn('messages', 'reply_count')) {
                $table->dropColumn('reply_count');
            }
        });
    }
};
