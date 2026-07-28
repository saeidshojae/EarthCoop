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
        Schema::table('groups', function (Blueprint $table) {
            // اضافه کردن ستون‌های موردنیاز
            if (!Schema::hasColumn('groups', 'specialty_id')) {
                $table->unsignedBigInteger('specialty_id')->nullable()->after('category');
                $table->foreign('specialty_id')->references('id')->on('occupational_fields')->onDelete('set null');
            }
            if (!Schema::hasColumn('groups', 'experience_id')) {
                $table->unsignedBigInteger('experience_id')->nullable()->after('specialty_id');
                $table->foreign('experience_id')->references('id')->on('experience_fields')->onDelete('set null');
            }
            if (!Schema::hasColumn('groups', 'age_group_id')) {
                $table->unsignedBigInteger('age_group_id')->nullable()->after('experience_id');
                $table->foreign('age_group_id')->references('id')->on('age_groups')->onDelete('set null');
            }
            if (!Schema::hasColumn('groups', 'location_level')) {
                $table->string('location_level')->nullable()->after('location_id');
            }
            if (!Schema::hasColumn('groups', 'address_id')) {
                $table->unsignedBigInteger('address_id')->nullable()->after('location_level');
                $table->foreign('address_id')->references('id')->on('addresses')->onDelete('set null');
            }
            if (!Schema::hasColumn('groups', 'is_open')) {
                $table->boolean('is_open')->default(true)->after('name');
            }
            if (!Schema::hasColumn('groups', 'gender')) {
                $table->string('gender')->nullable()->after('age_group_id');
            }
            if (!Schema::hasColumn('groups', 'age_group_title')) {
                $table->string('age_group_title')->nullable()->after('gender');
            }
            if (!Schema::hasColumn('groups', 'description')) {
                $table->text('description')->nullable()->after('age_group_title');
            }
            if (!Schema::hasColumn('groups', 'avatar')) {
                $table->string('avatar')->nullable()->after('description');
            }
            if (!Schema::hasColumn('groups', 'last_activity_at')) {
                $table->timestamp('last_activity_at')->nullable()->after('updated_at');
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
        Schema::table('groups', function (Blueprint $table) {
            //
        });
    }
};
