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
        if (Schema::hasTable('elections')) {
            return;
        }
        Schema::create('elections', function (Blueprint $table) {
            $table->id();
            if (Schema::hasTable('groups')) {
                $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            } else {
                $table->unsignedBigInteger('group_id')->nullable();
                $table->index('group_id');
            }
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_closed')->default(false); // آیا انتخابات بسته شده؟
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('elections');
    }
};
