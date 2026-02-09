<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('najm_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('from_account_id')->nullable()->index();
            $table->unsignedBigInteger('to_account_id')->nullable()->index();
            $table->bigInteger('amount')->default(0);
            $table->string('type')->default('immediate');
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('najm_transactions');
    }
};
