<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_schemas', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->char('country_code', 2)->nullable()->index();
            $table->string('name');
            $table->string('version')->default('1');
            $table->string('status')->default('active')->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('location_types', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('canonical_name');
            $table->boolean('is_residence_endpoint')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('location_schema_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_schema_id')->constrained('location_schemas')->cascadeOnDelete();
            $table->foreignId('location_type_id')->constrained('location_types')->cascadeOnDelete();
            $table->boolean('is_root')->default(false);
            $table->boolean('is_residence_endpoint')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['location_schema_id', 'location_type_id'], 'location_schema_type_unique');
        });

        Schema::create('location_type_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_schema_id')->constrained('location_schemas')->cascadeOnDelete();
            $table->foreignId('parent_type_id')->constrained('location_types')->cascadeOnDelete();
            $table->foreignId('child_type_id')->constrained('location_types')->cascadeOnDelete();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(
                ['location_schema_id', 'parent_type_id', 'child_type_id'],
                'location_type_relation_unique'
            );
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('location_schema_id')->nullable()->after('parent_id')->constrained('location_schemas')->nullOnDelete();
            $table->foreignId('location_type_id')->nullable()->after('location_schema_id')->constrained('location_types')->nullOnDelete();
            $table->char('country_code', 2)->nullable()->after('location_type_id')->index();
            $table->string('canonical_name')->nullable()->after('name');
            $table->json('localized_names')->nullable()->after('canonical_name');
            $table->string('status')->default('active')->after('level')->index();
            $table->decimal('centroid_latitude', 10, 7)->nullable()->after('status');
            $table->decimal('centroid_longitude', 10, 7)->nullable()->after('centroid_latitude');
            $table->dateTime('valid_from')->nullable()->after('centroid_longitude');
            $table->dateTime('valid_to')->nullable()->after('valid_from');
            $table->json('provenance')->nullable()->after('valid_to');
            $table->json('metadata')->nullable()->after('provenance');
        });

        // Preserve every legacy location ID while giving pre-existing rows a
        // canonical display name. Schema/type assignment is intentionally left
        // nullable until the versioned reference importer maps legacy data.
        DB::table('locations')
            ->whereNull('canonical_name')
            ->update(['canonical_name' => DB::raw('name')]);

        Schema::create('location_external_ids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('source');
            $table->string('dataset_version')->nullable();
            $table->string('external_id');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['source', 'dataset_version', 'external_id'], 'location_external_identity_unique');
            $table->index(['location_id', 'source']);
        });

        Schema::create('location_relations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_location_id')->constrained('locations')->cascadeOnDelete();
            $table->foreignId('to_location_id')->constrained('locations')->cascadeOnDelete();
            $table->string('relation_type');
            $table->dateTime('effective_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(
                ['from_location_id', 'to_location_id', 'relation_type'],
                'location_relation_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_relations');
        Schema::dropIfExists('location_external_ids');

        Schema::table('locations', function (Blueprint $table) {
            $table->dropForeign(['location_schema_id']);
            $table->dropForeign(['location_type_id']);
            $table->dropColumn([
                'location_schema_id',
                'location_type_id',
                'country_code',
                'canonical_name',
                'localized_names',
                'status',
                'centroid_latitude',
                'centroid_longitude',
                'valid_from',
                'valid_to',
                'provenance',
                'metadata',
            ]);
        });

        Schema::dropIfExists('location_type_relations');
        Schema::dropIfExists('location_schema_types');
        Schema::dropIfExists('location_types');
        Schema::dropIfExists('location_schemas');
    }
};
