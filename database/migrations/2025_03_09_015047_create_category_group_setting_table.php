<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_group_setting', function (Blueprint $table) {
            // id int(11) Not Null, Auto Increment
            $table->integerIncrements('id');

            // category_id int(11) Not Null
            $table->integer('category_id');

            // group_setting_id int(11) Not Null
            $table->integer('group_setting_id');

            // created_at datetime Not Null
            $table->datetime('created_at');

            // updated_at datetime Null, Default NULL
            $table->datetime('updated_at')->nullable();

            // No index defined (foreign keys not added)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_group_setting');
    }
};