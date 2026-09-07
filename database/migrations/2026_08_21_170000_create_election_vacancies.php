<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('election_vacancies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('election_id')->constrained('elections')->cascadeOnDelete();
            $table->foreignId('source_appointment_id')->unique()->constrained('election_appointments')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('group_id')->constrained('groups')->cascadeOnDelete();
            $table->string('position', 32);
            $table->string('status', 32)->default('open');
            $table->timestamp('opened_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('replacement_offer_id')->nullable()->constrained('election_responsibility_offers')->nullOnDelete();
            $table->foreignId('replacement_appointment_id')->nullable()->constrained('election_appointments')->nullOnDelete();
            $table->string('actor', 128)->default('election_appointment_service');
            $table->string('reason', 255);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'opened_at'], 'election_vacancies_status_opened_index');
            $table->index(['election_id', 'position', 'status'], 'election_vacancies_election_position_status_index');
            $table->index(['group_id', 'position', 'status'], 'election_vacancies_group_position_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('election_vacancies');
    }
};
