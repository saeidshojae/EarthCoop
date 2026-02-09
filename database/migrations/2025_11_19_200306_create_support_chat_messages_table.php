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
        Schema::create('support_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('support_chat_id')->index();
            $table->unsignedBigInteger('user_id')->index(); // فرستنده پیام
            $table->enum('type', ['user', 'agent', 'system'])->default('user');
            $table->text('message');
            $table->json('attachments')->nullable(); // لیست فایل‌های ضمیمه
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->foreign('support_chat_id')->references('id')->on('support_chats')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('support_chat_messages');
    }
};
