<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_documents', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 100)->unique();
            $table->string('title');
            $table->string('source_type', 32); // terms or najm_bahar_agreements
            $table->unsignedBigInteger('source_root_id')->nullable();
            $table->boolean('is_staged_import')->default(false);
            $table->timestamps();
        });

        Schema::create('legal_document_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_document_id')->constrained('legal_documents')->restrictOnDelete();
            $table->string('version_label', 40);
            $table->string('language', 10)->default('fa');
            $table->string('status', 20)->default('draft'); // draft, published, retired
            $table->longText('content_snapshot')->nullable();
            $table->char('content_sha256', 64)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['legal_document_id', 'version_label', 'language'], 'legal_versions_identity_unique');
            $table->index(['legal_document_id', 'status']);
        });

        Schema::create('legal_document_acceptances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('legal_document_version_id')->constrained('legal_document_versions')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->string('context', 50);
            $table->string('locale', 10)->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'legal_document_version_id', 'context'], 'legal_acceptance_once_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_document_acceptances');
        Schema::dropIfExists('legal_document_versions');
        Schema::dropIfExists('legal_documents');
    }
};
