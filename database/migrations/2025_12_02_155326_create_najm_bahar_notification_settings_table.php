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
        Schema::create('najm_bahar_notification_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->onDelete('cascade');
            $table->boolean('transaction_notifications')->default(true);
            $table->boolean('low_balance_notifications')->default(true);
            $table->boolean('large_transaction_notifications')->default(true);
            $table->boolean('scheduled_transaction_notifications')->default(true);
            $table->integer('low_balance_threshold')->default(1000);
            $table->integer('large_transaction_threshold')->default(100000);
            $table->boolean('email_notifications')->default(false);
            $table->timestamps();
            
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('najm_bahar_notification_settings');
    }
};
