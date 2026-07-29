<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('group_setting', function (Blueprint $table) {
            // id int(11) Not Null
            $table->id();

            // level varchar(255) Not Null
            $table->string('level', 255);

            // manager_count int(11) Not Null
            $table->integer('manager_count');

            // inspector_count int(11) Not Null
            $table->integer('inspector_count');

            // election_time int(11) Not Null
            $table->integer('election_time');

            // max_for_election int(11) Not Null, Default: 1
            $table->integer('max_for_election')->default(1);

            // election_status int(11) Not Null, Default: 1
            $table->integer('election_status')->default(1);

            // second_election_time varchar(255) Null, Default: NULL
            $table->string('second_election_time', 255)->nullable();

            // created_at datetime Not Null
            $table->datetime('created_at');

            // updated_at datetime Null, Default: NULL
            $table->datetime('updated_at')->nullable();

            // هیچ ایندکسی تعریف نشده (طبق دیتا دیکشنری)
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('group_setting');
    }
};