<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('group_chat_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('scope', 150);
            $table->string('idempotency_key', 100);
            $table->char('request_hash', 64);
            $table->string('state', 20)->default('processing');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['user_id', 'scope', 'idempotency_key'], 'group_chat_idempotency_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_chat_idempotency_keys');
    }
};
