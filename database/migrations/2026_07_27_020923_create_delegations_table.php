<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delegations', function (Blueprint $table) {
            // id int(11) Not Null, Auto Increment
            $table->integerIncrements('id');

            // user_id int(11) Not Null
            $table->integer('user_id');

            // expert_id int(11) Not Null
            $table->integer('expert_id');

            // poll_id int(11) Not Null
            $table->integer('poll_id');

            // created_at datetime Not Null
            $table->datetime('created_at');

            // updated_at datetime Null, Default NULL
            $table->datetime('updated_at')->nullable();

            // deleted_at datetime Null, Default NULL (برای حذف نرم)
            $table->datetime('deleted_at')->nullable();

            // No index defined (دقیقاً مطابق دیتا دیکشنری)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('delegations');
    }
};