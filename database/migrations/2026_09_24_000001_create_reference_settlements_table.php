<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reference_settlements', function (Blueprint $table): void {
            $table->id();
            $table->string('source', 64);
            $table->string('dataset_version', 24);
            $table->string('external_id', 80);
            $table->string('parent_external_id', 80);
            $table->string('source_code', 80);
            $table->unsignedBigInteger('source_row_id');
            $table->string('name_fa', 255);
            $table->string('search_name', 255);
            $table->string('classification', 48)->default('unverified_settlement');
            $table->string('residential_eligibility', 32)->default('unverified');
            $table->boolean('governance_authorized')->default(false);
            $table->boolean('operational_promotion_allowed')->default(false);
            $table->json('provenance');
            $table->timestamps();

            $table->unique(['source', 'dataset_version', 'external_id'], 'reference_settlements_identity_uq');
            $table->index(['source', 'dataset_version', 'parent_external_id'], 'reference_settlements_parent_idx');
            $table->index(['source', 'dataset_version', 'source_code'], 'reference_settlements_code_idx');
            $table->index(['source', 'dataset_version', 'search_name', 'id'], 'reference_settlements_search_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reference_settlements');
    }
};
