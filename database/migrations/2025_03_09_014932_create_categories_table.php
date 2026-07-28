<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            // id int(11) Not Null, Auto Increment
            $table->integerIncrements('id');

            // name varchar(255) Not Null
            $table->string('name', 255);

            // description text Null, Default NULL
            $table->text('description')->nullable();

            // created_at datetime Not Null
            $table->datetime('created_at');

            // updated_at datetime Null, Default NULL
            $table->datetime('updated_at')->nullable();

            // No index defined
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};