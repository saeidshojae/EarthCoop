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
        Schema::create('support_chats', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('agent_id')->nullable()->index(); // پشتیبان اختصاص یافته
            $table->unsignedBigInteger('ticket_id')->nullable()->index(); // اگر تبدیل به تیکت شد
            $table->enum('status', ['waiting', 'active', 'resolved', 'closed'])->default('waiting')->index();
            $table->enum('priority', ['low', 'normal', 'high'])->default('normal');
            $table->string('subject')->nullable(); // موضوع چت
            $table->timestamp('resolved_at')->nullable();
            $table->timestamp('last_activity_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('agent_id')->references('id')->on('users')->onDelete('set null');
            $table->foreign('ticket_id')->references('id')->on('tickets')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('support_chats');
    }
};
