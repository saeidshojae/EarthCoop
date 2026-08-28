<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('founder_admin_setting_intents', function (Blueprint $table) {
            $table->id();
            $table->string('idempotency_key', 64)->unique();
            $table->string('setting_key', 100);
            $table->json('setting_value');
            $table->unsignedBigInteger('requested_by')->nullable();
            $table->string('reason_code')->nullable();
            $table->string('status', 20)->default('pending');
            $table->unsignedBigInteger('executed_by')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['setting_key', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('founder_admin_setting_intents');
    }
};
