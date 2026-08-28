<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('founder_group_role_change_intents', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_user_id')->index();
            $table->unsignedTinyInteger('target_role');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('requested_by')->index();
            $table->string('reason_code')->nullable();
            $table->string('idempotency_key', 128)->unique();
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedBigInteger('executed_by')->nullable()->index();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->foreign('group_user_id')->references('id')->on('group_user')->cascadeOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('executed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('founder_group_role_change_intents');
    }
};
