<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupUserTable extends Migration
{
    public function up()
    {
        Schema::create('group_user', function (Blueprint $table) {

            $table->id();

            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('user_id');

            // نقش عضو
            $table->tinyInteger('role')->default(0);

            // وضعیت عضویت
            $table->tinyInteger('status')->default(1);

            // پایان اعتبار عضویت
            $table->timestamp('expired')->nullable();

            // آخرین پیام خوانده‌شده
            $table->unsignedBigInteger('last_read_message_id')->nullable();

            $table->timestamps();
            $table->softDeletes();

            /*
            |--------------------------------------------------------------------------
            | Indexes
            |--------------------------------------------------------------------------
            */

            $table->unique(['group_id', 'user_id']);

            $table->index('last_read_message_id');

            /*
            |--------------------------------------------------------------------------
            | Foreign Keys
            |--------------------------------------------------------------------------
            */

            $table->foreign('group_id')
                ->references('id')
                ->on('groups')
                ->cascadeOnDelete();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreign('last_read_message_id')
                ->references('id')
                ->on('messages')
                ->nullOnDelete();
        });
    }

    public function down()
    {
        Schema::dropIfExists('group_user');
    }
}