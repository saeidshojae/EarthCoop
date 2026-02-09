<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('najm_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 32)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('type')->default('user');
            $table->bigInteger('balance')->default(0);
            $table->json('meta')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('najm_accounts');
    }
};
