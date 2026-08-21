<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secretariat_record_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('record_id')->constrained('secretariat_records')->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('title', 500);
            $table->string('subject', 500)->nullable();
            $table->text('summary')->nullable();
            $table->longText('body')->nullable();
            $table->text('change_reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->char('content_checksum', 64)->nullable();
            $table->boolean('is_official')->default(false);
            $table->timestamps();

            $table->unique(['record_id', 'version_number'], 'secretariat_versions_record_number_unique');
            $table->index(['record_id', 'is_official'], 'secretariat_versions_record_official_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secretariat_record_versions');
    }
};
