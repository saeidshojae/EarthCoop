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
        if (Schema::hasTable('votes')) {
            return; // جدول از قبل وجود دارد
        }
        Schema::create('votes', function (Blueprint $table) {
            $table->id();
            // از FK صرفنظر می‌کنیم تا با جداولی که موتورشان InnoDB نیست تداخل نداشته باشد
            $table->unsignedBigInteger('election_id');
            $table->unsignedBigInteger('voter_id'); // رأی‌دهنده
            $table->unsignedBigInteger('candidate_id'); // به کدام کاندیدا
            $table->timestamps();

            // ایندکس‌های کاربردی
            $table->index(['election_id']);
            $table->index(['voter_id']);
            $table->index(['candidate_id']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('votes');
    }
};
