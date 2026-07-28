<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGroupsTable extends Migration
{
    public function up()
    {
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('group_type')->default('0'); // '0' for general groups
            $table->string('name');       // مثلاً "گروه عمومی شهر تهران"
            $table->string('category')->nullable(); // برای تخصص یا جنسیت یا رده سنی
            $table->unsignedBigInteger('location_id')->nullable(); // در صورت وابستگی مکانی
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('groups');
    }
}
