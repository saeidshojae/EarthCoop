<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $schemaId = DB::table('location_schemas')->where('key', 'ir-reference-v1')->value('id');
        if (! $schemaId) {
            return;
        }

        $typeIds = DB::table('location_types')
            ->whereIn('key', ['street', 'alley', 'building'])
            ->pluck('id', 'key');

        if (! isset($typeIds['street'], $typeIds['alley'], $typeIds['building'])) {
            return;
        }

        foreach (['street', 'alley'] as $parentKey) {
            DB::table('location_type_relations')->updateOrInsert(
                [
                    'location_schema_id' => $schemaId,
                    'parent_type_id' => $typeIds[$parentKey],
                    'child_type_id' => $typeIds['building'],
                ],
                [
                    'metadata' => json_encode(['source' => '2026-09-18-flexible-micro-location-paths']),
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }

    public function down(): void
    {
        $schemaId = DB::table('location_schemas')->where('key', 'ir-reference-v1')->value('id');
        if (! $schemaId) {
            return;
        }

        $typeIds = DB::table('location_types')
            ->whereIn('key', ['street', 'alley', 'building'])
            ->pluck('id', 'key');

        if (! isset($typeIds['street'], $typeIds['alley'], $typeIds['building'])) {
            return;
        }

        DB::table('location_type_relations')
            ->where('location_schema_id', $schemaId)
            ->whereIn('parent_type_id', [$typeIds['street'], $typeIds['alley']])
            ->where('child_type_id', $typeIds['building'])
            ->where('metadata->source', '2026-09-18-flexible-micro-location-paths')
            ->delete();
    }
};
