<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('governance_areas', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('governance_areas')->nullOnDelete();
            $table->string('key')->unique();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('governance_type')->nullable()->index();
            $table->string('area_kind')->default('official')->index();
            $table->string('canonical_name')->nullable();
            $table->json('localized_names')->nullable();
            $table->unsignedInteger('rank')->nullable()->index();
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('governance_area_locations', function (Blueprint $table): void {
            $table->foreignId('governance_area_id')->constrained('governance_areas')->cascadeOnDelete();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['governance_area_id', 'location_id']);
            $table->index(['location_id', 'governance_area_id']);
        });

        Schema::create('governance_capability_policies', function (Blueprint $table): void {
            $table->id();
            $table->string('scope')->index();
            $table->string('country_code', 2)->nullable()->index();
            $table->string('governance_type')->nullable()->index();
            $table->json('capabilities');
            $table->timestamps();

            $table->index(['scope', 'country_code', 'governance_type'], 'governance_capability_policy_lookup');
        });

        Schema::create('governance_area_overrides', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('governance_area_id')->unique()->constrained('governance_areas')->cascadeOnDelete();
            $table->json('capabilities');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governance_area_overrides');
        Schema::dropIfExists('governance_capability_policies');
        Schema::dropIfExists('governance_area_locations');
        Schema::dropIfExists('governance_areas');
    }
};
