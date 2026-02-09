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
        if (Schema::hasTable('candidates')) {
            return;
        }
        Schema::create('candidates', function (Blueprint $table) {
            $table->id();
            if (Schema::hasTable('elections')) {
                $table->foreignId('election_id')->constrained('elections')->onDelete('cascade');
            } else {
                $table->unsignedBigInteger('election_id');
                $table->index('election_id');
            }
            if (Schema::hasTable('users')) {
                $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            } else {
                $table->unsignedBigInteger('user_id');
                $table->index('user_id');
            }
            $table->enum('position', ['manager', 'inspector']); // مدیر یا بازرس
            $table->enum('accept_status', ['accepted', 'declined'])->nullable(); // بعد از رأی‌گیری اعلام می‌کنه که قبول کرده یا نه
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('candidates');
    }
};
