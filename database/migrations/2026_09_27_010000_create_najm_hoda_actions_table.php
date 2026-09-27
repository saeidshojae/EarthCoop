<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('najm_hoda_actions', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('conversation_id')->nullable()->constrained('conversations')->nullOnDelete();
            $table->string('action', 120);
            $table->unsignedInteger('contract_version')->default(1);
            $table->string('risk', 24)->default('low');
            $table->string('mode', 24)->default('propose');
            $table->json('input');
            $table->char('input_hash', 64);
            $table->json('expected_output')->nullable();
            $table->string('status', 24)->default('proposed');
            $table->boolean('consent_required')->default(true);
            $table->timestamp('consented_at')->nullable();
            $table->uuid('consent_evidence_id')->nullable()->unique();
            $table->string('apply_idempotency_key', 100)->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->uuid('evidence_id')->nullable()->unique();
            $table->string('runtime_run_id', 100)->nullable();
            $table->json('result')->nullable();
            $table->string('error_code', 120)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->unique(['user_id', 'apply_idempotency_key'], 'najm_hoda_actions_user_apply_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('najm_hoda_actions');
    }
};
