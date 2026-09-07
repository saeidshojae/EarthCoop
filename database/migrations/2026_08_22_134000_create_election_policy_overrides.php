<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_policy_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
            $table->foreignId('from_policy_version_id')->constrained('election_policy_versions')->restrictOnDelete();
            $table->foreignId('to_policy_version_id')->constrained('election_policy_versions')->restrictOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('reason', 1000);
            $table->string('lifecycle_status', 48);
            $table->json('metadata')->nullable();
            $table->dateTime('applied_at');
            $table->timestamps();
            $table->index(['election_id', 'applied_at'], 'election_policy_override_audit_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_policy_overrides');
    }
};
