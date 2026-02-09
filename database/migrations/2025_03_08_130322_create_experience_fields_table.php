<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateExperienceFieldsTable extends Migration
{
    public function up()
    {
        Schema::create('experience_fields', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // مثلاً "مهندسی"، "مهندسی کامپیوتر"، "برنامه نویسی"
            $table->unsignedBigInteger('parent_id')->nullable();
            $table->foreign('parent_id')->references('id')->on('experience_fields')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('experience_fields');
    }
}
