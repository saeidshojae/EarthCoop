<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->after('id');
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->after('first_name');
            }
            if (!Schema::hasColumn('users', 'nationality')) {
                $table->string('nationality')->after('last_name');
            }
            if (!Schema::hasColumn('users', 'national_id')) {
                $table->string('national_id')->after('nationality');
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->after('national_id');
            }
            if (!Schema::hasColumn('users', 'birth_date')) {
                $table->date('birth_date')->after('phone');
            }
            if (!Schema::hasColumn('users', 'gender')) {
                $table->enum('gender', ['male', 'female', 'other'])->after('birth_date');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['first_name', 'last_name', 'nationality', 'national_id', 'phone', 'birth_date', 'gender']);
        });
    }
};