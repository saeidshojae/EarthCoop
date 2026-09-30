<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('communication_sender_identities', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('email');
            $table->string('display_name');
            $table->string('reply_to')->nullable();
            $table->string('purpose')->nullable();
            $table->string('system_identity_key')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
            $table->index(['is_active', 'is_default']);
        });

        Schema::create('communication_templates', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('classification');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['classification', 'is_active']);
        });

        Schema::create('communication_template_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('communication_template_id');
            $table->unsignedInteger('version');
            $table->string('locale', 16)->default('fa');
            $table->string('subject');
            $table->longText('body');
            $table->json('variables_schema')->nullable();
            $table->foreignId('communication_sender_identity_id')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->foreignId('approved_by')->nullable();
            $table->timestamps();

            $table->foreign('communication_template_id', 'cc_tv_template_fk')
                ->references('id')->on('communication_templates')->cascadeOnDelete();
            $table->foreign('communication_sender_identity_id', 'cc_tv_sender_fk')
                ->references('id')->on('communication_sender_identities')->restrictOnDelete();
            $table->foreign('created_by', 'cc_tv_created_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->foreign('approved_by', 'cc_tv_approved_by_fk')
                ->references('id')->on('users')->nullOnDelete();
            $table->unique(
                ['communication_template_id', 'locale', 'version'],
                'cc_tv_template_locale_version_uq'
            );
            $table->index(['locale', 'published_at'], 'cc_tv_locale_published_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('communication_template_versions');
        Schema::dropIfExists('communication_templates');
        Schema::dropIfExists('communication_sender_identities');
    }
};
