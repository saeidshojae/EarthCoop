<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('election_lifecycle_transitions')) {
            return;
        }

        Schema::create('election_lifecycle_transitions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
            $table->string('from_status', 40);
            $table->string('to_status', 40);
            $table->string('reason', 160);
            $table->string('source', 80)->default('system');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('reference', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('transitioned_at');
            $table->timestamps();

            $table->index(['election_id', 'transitioned_at'], 'election_lifecycle_transition_time_index');
            $table->index(['election_id', 'to_status'], 'election_lifecycle_transition_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_lifecycle_transitions');
    }
};
