<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pending_residence_intents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('anchor_relationship_id')->constrained('user_location_relationships')->cascadeOnDelete();
            $table->foreignId('location_proposal_id')->constrained('location_proposals')->cascadeOnDelete();
            $table->foreignId('resolved_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('status')->default('pending')->index();
            $table->dateTime('selected_at')->index();
            $table->dateTime('resolved_at')->nullable()->index();
            $table->dateTime('cancelled_at')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'pending_residence_intents_user_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pending_residence_intents');
    }
};
