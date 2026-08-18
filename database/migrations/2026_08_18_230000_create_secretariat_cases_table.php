<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('office_id')->constrained('secretariat_offices')->restrictOnDelete();
            $table->string('case_number', 160);
            $table->string('title', 500);
            $table->text('summary')->nullable();
            $table->enum('status', ['open', 'on_hold', 'closed', 'archived'])->default('open');
            $table->enum('confidentiality', ['public', 'office_members', 'leadership', 'restricted', 'confidential'])->default('office_members');
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['office_id', 'case_number']);
            $table->index(['office_id', 'status']);
            $table->index(['office_id', 'confidentiality']);
        });

        Schema::create('secretariat_case_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('secretariat_cases')->restrictOnDelete();
            $table->foreignId('record_id')->constrained('secretariat_records')->restrictOnDelete();
            $table->string('role', 80)->default('related');
            $table->foreignId('added_by')->constrained('users');
            $table->timestamp('added_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['case_id', 'record_id']);
            $table->index(['record_id', 'case_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_case_records');
        Schema::dropIfExists('secretariat_cases');
    }
};
