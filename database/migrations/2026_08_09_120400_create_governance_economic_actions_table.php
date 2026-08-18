<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_economic_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resolution_id')->constrained('governance_resolutions')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('eligibility_snapshot_id')->nullable()->constrained('governance_eligibility_snapshots')->nullOnDelete();
            $table->string('action_type', 80);
            $table->string('status', 30)->default('pending');
            $table->string('idempotency_key', 191)->unique();
            $table->json('payload');
            $table->json('result')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('claimed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['resolution_id', 'status']);
            $table->index(['action_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_economic_actions');
    }
};
