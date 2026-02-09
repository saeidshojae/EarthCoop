<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('springs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->bigInteger('amount')->default(0);
            $table->tinyInteger('status')->default(0);
            $table->string('cart_number')->unique();
            $table->timestamps();
            
            $table->index('user_id');
            $table->index('cart_number');
        });
        
        // ایجاد حساب پیش‌فرض EarthCoop
        DB::table('springs')->insert([
            'name' => 'حساب EarthCoop',
            'user_id' => null,
            'amount' => 0,
            'status' => 1,
            'cart_number' => '0000000000',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('springs');
    }
};
