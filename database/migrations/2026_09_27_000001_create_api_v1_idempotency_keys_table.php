<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_v1_idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('actor_key', 128);
            $table->string('scope', 180);
            $table->string('idempotency_key', 100);
            $table->string('request_hash', 64);
            $table->string('state', 20);
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->timestamps();
            $table->timestamp('expires_at')->nullable();
            $table->unique(['actor_key', 'scope', 'idempotency_key'], 'api_v1_idem_actor_scope_key_unique');
            $table->index('expires_at', 'api_v1_idem_expires_at_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_v1_idempotency_keys');
    }
};
