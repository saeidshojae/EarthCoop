<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateOccupationalFieldsTable extends Migration
{
    public function up()
    {
        Schema::create('occupational_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // مثلاً "فرهنگی"، "معلم"، "معلم ابتدایی"
            $table->unsignedBigInteger('parent_id')->nullable(); // جهت سلسله‌مراتب
            $table->foreign('parent_id')->references('id')->on('occupational_fields')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('occupational_fields');
    }
}
