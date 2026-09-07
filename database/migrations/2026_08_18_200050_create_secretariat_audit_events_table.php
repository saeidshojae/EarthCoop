<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_audit_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained('secretariat_offices')->restrictOnDelete();
            $table->foreignId('record_id')->nullable()->constrained('secretariat_records')->nullOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 64);
            $table->timestamp('event_at');
            $table->json('metadata')->nullable();
            $table->string('request_ip', 64)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['record_id', 'event_at'], 'secretariat_audit_record_time_idx');
            $table->index(['office_id', 'event_at'], 'secretariat_audit_office_time_idx');
            $table->index(['event_type', 'event_at'], 'secretariat_audit_type_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_audit_events');
    }
};
