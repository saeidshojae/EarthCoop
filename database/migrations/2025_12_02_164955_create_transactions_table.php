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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_account_id')->nullable()->constrained('springs')->onDelete('cascade');
            $table->foreignId('to_account_id')->nullable()->constrained('springs')->onDelete('cascade');
            $table->bigInteger('amount');
            $table->text('description')->nullable();
            $table->timestamps();
            
            $table->index('from_account_id');
            $table->index('to_account_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('transactions');
    }
};
