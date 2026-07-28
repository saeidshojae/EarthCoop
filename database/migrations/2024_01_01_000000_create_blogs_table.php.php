<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('content');
            $table->string('img')->nullable();
            $table->integer('user_id');
            $table->integer('group_id');
            $table->integer('category_id');
            $table->string('file_type')->nullable();
            $table->longText('read_by')->nullable()->comment('JSON array of user_id => timestamp');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blogs');
    }
};