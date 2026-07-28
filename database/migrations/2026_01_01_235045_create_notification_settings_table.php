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
        Schema::create('notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            
            // Group notifications
            $table->boolean('group_post')->default(true)->comment('اعلان پست جدید در گروه');
            $table->boolean('group_poll')->default(true)->comment('اعلان نظرسنجی جدید در گروه');
            $table->boolean('group_comment_new')->default(true)->comment('اعلان کامنت جدید روی پست');
            $table->boolean('group_comment_reply')->default(true)->comment('اعلان پاسخ به کامنت');
            $table->boolean('group_invitation')->default(true)->comment('اعلان دعوت به گروه');
            
            // Election notifications
            $table->boolean('election_started')->default(true)->comment('اعلان شروع انتخابات');
            $table->boolean('election_finished')->default(true)->comment('اعلان پایان انتخابات');
            $table->boolean('election_elected')->default(true)->comment('اعلان انتخاب شدن در انتخابات');
            $table->boolean('election_accepted')->default(true)->comment('اعلان قبول مسئولیت');
            $table->boolean('election_reminder')->default(true)->comment('اعلان یادآوری شرکت در انتخابات');
            
            // Chat notifications
            $table->boolean('chat_message')->default(true)->comment('اعلان پیام جدید در چت');
            $table->boolean('chat_reply')->default(true)->comment('اعلان پاسخ به پیام');
            $table->boolean('chat_mention')->default(true)->comment('اعلان mention شدن');
            
            // Auction notifications
            $table->boolean('auction_started')->default(true)->comment('اعلان شروع حراج');
            $table->boolean('auction_ended')->default(true)->comment('اعلان پایان حراج');
            $table->boolean('auction_bid')->default(true)->comment('اعلان پیشنهاد جدید در حراج');
            $table->boolean('auction_won')->default(true)->comment('اعلان برنده شدن در حراج');
            $table->boolean('auction_outbid')->default(true)->comment('اعلان پیشنهاد بالاتر از پیشنهاد کاربر');
            
            // Admin notifications
            $table->boolean('admin_message')->default(true)->comment('اعلان پیام از ادمین');
            
            // General settings
            $table->boolean('email_notifications')->default(false)->comment('ارسال اعلان‌ها از طریق ایمیل');
            $table->boolean('push_notifications')->default(true)->comment('ارسال اعلان‌ها به صورت push');
            
            $table->timestamps();
            
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('notification_settings');
    }
};
