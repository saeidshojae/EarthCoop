<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            if (!Schema::hasColumn('najm_bahar_projects', 'geographic_continent_id')) {
                $table->unsignedBigInteger('geographic_continent_id')->nullable();
            }
            if (!Schema::hasColumn('najm_bahar_projects', 'geographic_country_id')) {
                $table->unsignedBigInteger('geographic_country_id')->nullable();
            }
            if (!Schema::hasColumn('najm_bahar_projects', 'geographic_province_id')) {
                $table->unsignedBigInteger('geographic_province_id')->nullable();
            }
            if (!Schema::hasColumn('najm_bahar_projects', 'geographic_county_id')) {
                $table->unsignedBigInteger('geographic_county_id')->nullable();
            }
            if (!Schema::hasColumn('najm_bahar_projects', 'geographic_section_id')) {
                $table->unsignedBigInteger('geographic_section_id')->nullable();
            }
            if (!Schema::hasColumn('najm_bahar_projects', 'geographic_city_id')) {
                $table->unsignedBigInteger('geographic_city_id')->nullable();
            }
            if (!Schema::hasColumn('najm_bahar_projects', 'geographic_rural_id')) {
                $table->unsignedBigInteger('geographic_rural_id')->nullable();
            }
            if (!Schema::hasColumn('najm_bahar_projects', 'geographic_region_id')) {
                $table->unsignedBigInteger('geographic_region_id')->nullable();
            }
            if (!Schema::hasColumn('najm_bahar_projects', 'geographic_neighborhood_id')) {
                $table->unsignedBigInteger('geographic_neighborhood_id')->nullable();
            }
            if (!Schema::hasColumn('najm_bahar_projects', 'geographic_street_id')) {
                $table->unsignedBigInteger('geographic_street_id')->nullable();
            }
            if (!Schema::hasColumn('najm_bahar_projects', 'geographic_alley_id')) {
                $table->unsignedBigInteger('geographic_alley_id')->nullable();
            }
        });

        $this->createIndexIfMissing(
            'najm_bahar_projects',
            'najm_bahar_projects_geo_cont_country_prov_idx',
            ['geographic_continent_id', 'geographic_country_id', 'geographic_province_id']
        );
        $this->createIndexIfMissing(
            'najm_bahar_projects',
            'najm_bahar_projects_geo_neighborhood_idx',
            ['geographic_neighborhood_id']
        );
        $this->createIndexIfMissing(
            'najm_bahar_projects',
            'najm_bahar_projects_geo_region_idx',
            ['geographic_region_id']
        );
    }

    public function down(): void
    {
        $this->dropIndexIfExists('najm_bahar_projects', 'najm_bahar_projects_geo_cont_country_prov_idx');
        $this->dropIndexIfExists('najm_bahar_projects', 'najm_bahar_projects_geo_neighborhood_idx');
        $this->dropIndexIfExists('najm_bahar_projects', 'najm_bahar_projects_geo_region_idx');

        Schema::table('najm_bahar_projects', function (Blueprint $table) {
            foreach ([
                'geographic_continent_id',
                'geographic_country_id',
                'geographic_province_id',
                'geographic_county_id',
                'geographic_section_id',
                'geographic_city_id',
                'geographic_rural_id',
                'geographic_region_id',
                'geographic_neighborhood_id',
                'geographic_street_id',
                'geographic_alley_id',
            ] as $column) {
                if (Schema::hasColumn('najm_bahar_projects', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function createIndexIfMissing(string $table, string $indexName, array $columns): void
    {
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();

        if (!$exists) {
            Schema::table($table, function (Blueprint $table) use ($columns, $indexName) {
                $table->index($columns, $indexName);
            });
        }
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        $exists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', $table)
            ->where('index_name', $indexName)
            ->exists();

        if ($exists) {
            Schema::table($table, function (Blueprint $table) use ($indexName) {
                $table->dropIndex($indexName);
            });
        }
    }
};
