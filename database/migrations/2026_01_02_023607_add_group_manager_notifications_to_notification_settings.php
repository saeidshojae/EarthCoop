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
        Schema::table('notification_settings', function (Blueprint $table) {
            // Group manager/inspector notifications
            $table->boolean('group_report_message')->default(true)->after('group_invitation')->comment('اعلان گزارش پیام به مدیران و بازرسان');
            $table->boolean('group_chat_request')->default(true)->after('group_report_message')->comment('اعلان درخواست چت به مدیران گروه');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('notification_settings', function (Blueprint $table) {
            $table->dropColumn([
                'group_report_message',
                'group_chat_request',
            ]);
        });
    }
};
