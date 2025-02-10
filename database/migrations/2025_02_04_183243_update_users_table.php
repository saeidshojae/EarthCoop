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
            // اضافه کردن ستون‌های جدید
            $table->string('first_name')->after('id'); // نام
            $table->string('last_name')->after('first_name'); // نام خانوادگی
            $table->string('nationality')->after('last_name'); // ملیت
            $table->string('national_id')->unique()->after('nationality'); // کد ملی
            $table->string('phone')->unique()->after('national_id'); // شماره تلفن
            $table->date('birth_date')->after('phone'); // تاریخ تولد
            $table->enum('gender', ['male', 'female', 'other'])->after('birth_date'); // جنسیت
        });
    }
    
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            // حذف ستون‌های اضافه شده (برای Rollback)
            $table->dropColumn(['first_name', 'last_name', 'nationality', 'national_id', 'phone', 'birth_date', 'gender']);
        });
    }
};
