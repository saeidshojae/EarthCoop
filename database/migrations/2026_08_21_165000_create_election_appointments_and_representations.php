<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
            $table->foreignId('responsibility_offer_id')->constrained('election_responsibility_offers')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('position', 32);
            $table->unsignedTinyInteger('group_role');
            $table->string('appointment_kind', 32)->default('direct');
            $table->foreignId('source_appointment_id')->nullable()->constrained('election_appointments')->nullOnDelete();
            $table->string('status', 32)->default('active');
            $table->timestamp('appointed_at');
            $table->timestamp('ended_at')->nullable();
            $table->foreignId('superseded_by_appointment_id')->nullable()->constrained('election_appointments')->nullOnDelete();
            $table->string('actor', 128)->default('election_appointment_service');
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['responsibility_offer_id', 'group_id', 'position'],
                'election_appointments_offer_group_position_unique'
            );
            $table->index(['user_id', 'status'], 'election_appointments_user_status_index');
            $table->index(['group_id', 'position', 'status'], 'election_appointments_group_position_status_index');
            $table->index(['source_appointment_id', 'status'], 'election_appointments_source_status_index');
        });

        Schema::create('election_representation_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')->unique()->constrained('election_appointments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('source_group_id')->constrained('groups')->cascadeOnDelete();
            $table->foreignId('represented_group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('status', 32)->default('active');
            $table->timestamp('activated_at');
            $table->timestamp('ended_at')->nullable();
            $table->string('reason', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'election_representation_user_status_index');
            $table->index(['represented_group_id', 'status'], 'election_representation_target_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_representation_assignments');
        Schema::dropIfExists('election_appointments');
    }
};
