<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('najm_sub_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->string('sub_account_code', 64)->unique(); // e.g. 0000000000-001
            $table->string('name')->nullable();
            $table->bigInteger('balance')->default(0);
            $table->json('meta')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('najm_sub_accounts');
    }
};
