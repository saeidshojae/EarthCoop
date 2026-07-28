<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('account_number', 32)->unique();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('name')->nullable();
            $table->string('type')->default('user');
            $table->bigInteger('balance')->default(0);
            $table->bigInteger('balance_faded')->default(0);
            $table->bigInteger('balance_active')->default(0);
            $table->longText('meta')->nullable();
            $table->tinyInteger('status')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_accounts');
    }
};