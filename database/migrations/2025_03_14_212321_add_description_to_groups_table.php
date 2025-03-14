<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDescriptionToGroupsTable extends Migration
{
    public function up()
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->text('description')->nullable();
        });
    }

    public function down()
    {
        Schema::table('groups', function (Blueprint $table) {
            $table->dropColumn('description');
        });
    }
}
