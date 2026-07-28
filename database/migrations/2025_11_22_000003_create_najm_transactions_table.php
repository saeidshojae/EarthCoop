<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('tracking_number')->nullable()->unique();
            $table->unsignedBigInteger('from_account_id')->nullable()->index();
            $table->unsignedBigInteger('to_account_id')->nullable()->index();
            $table->bigInteger('amount')->default(0);
            $table->string('type')->default('immediate');
            $table->string('status')->default('pending');
            $table->timestamp('scheduled_at')->nullable();
            $table->string('money_state', 16)->default('active');
            $table->string('purpose', 64)->nullable();
            $table->longText('metadata')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_transactions');
    }
};