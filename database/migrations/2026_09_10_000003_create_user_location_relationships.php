<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_location_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('relationship_type')->index();
            $table->dateTime('started_at')->index();
            $table->dateTime('ended_at')->nullable()->index();
            $table->json('evidence')->nullable();
            $table->boolean('explicit_transfer')->default(false)->index();
            $table->boolean('transfer_override')->default(false);
            $table->foreignId('changed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('change_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'relationship_type', 'ended_at'], 'user_location_relationship_current_index');
            $table->index(['user_id', 'explicit_transfer', 'started_at'], 'user_location_transfer_window_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_location_relationships');
    }
};
