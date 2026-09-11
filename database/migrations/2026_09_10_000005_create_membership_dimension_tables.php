<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_dimensions', function (Blueprint $table): void {
            $table->id();
            $table->string('key', 64)->unique();
            $table->string('name');
            $table->string('resolver_class');
            $table->boolean('enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('group_creation_policies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('membership_dimension_id')
                ->constrained('membership_dimensions')
                ->cascadeOnDelete();
            $table->foreignId('governance_area_id')
                ->nullable()
                ->constrained('governance_areas')
                ->nullOnDelete();
            $table->string('governance_type', 64)->nullable();
            $table->unsignedInteger('governance_rank')->nullable();
            $table->string('mode', 32);
            $table->unsignedInteger('threshold')->nullable();
            $table->integer('priority')->default(0);
            $table->boolean('enabled')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['membership_dimension_id', 'enabled', 'priority'], 'gcp_dimension_enabled_priority_idx');
            $table->index(['governance_area_id', 'membership_dimension_id'], 'gcp_area_dimension_idx');
            $table->index(['governance_type', 'governance_rank'], 'gcp_governance_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('group_creation_policies');
        Schema::dropIfExists('membership_dimensions');
    }
};
