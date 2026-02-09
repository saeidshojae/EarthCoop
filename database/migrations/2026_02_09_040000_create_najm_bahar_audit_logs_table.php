<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_bahar_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->unsignedBigInteger('actor_user_id')->nullable()->index();
            $table->unsignedTinyInteger('actor_role')->nullable()->index();
            $table->string('action', 64)->index();
            $table->string('account_number', 32)->nullable()->index();
            $table->string('sub_account_code', 64)->nullable()->index();
            $table->bigInteger('amount')->nullable();
            $table->string('direction', 16)->nullable();
            $table->text('description')->nullable();
            $table->json('meta')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_bahar_audit_logs');
    }
};
