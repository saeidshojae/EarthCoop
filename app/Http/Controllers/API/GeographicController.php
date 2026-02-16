<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Continent;
use App\Models\Country;
use App\Models\Province;
use App\Models\County;
use App\Models\District;
use App\Models\City;
use App\Models\Region;
use App\Models\Neighborhood;
use App\Models\Street;
use App\Models\Alley;
use App\Models\Rural;
use App\Models\Village;
use Illuminate\Support\Facades\Schema;

class GeographicController extends Controller
{
    /**
     * دریافت لیست همه قاره‌ها
     */
    public function continents()
    {
        if (!Schema::hasTable('continents')) {
            return response()->json([]);
        }

        $query = Continent::query();
        $this->applyStatusFilter($query, 'continents');

        return response()->json($query->get(['id', 'name']));
    }

    /**
     * دریافت فرزندان یک منطقه بر اساس سطح
     */
    public function children($level, $parentId)
    {
        $level = strtolower($level);

        return match ($level) {
            'continent' => $this->getCountries($parentId),
            'country' => $this->getProvinces($parentId),
            'province' => $this->getCounties($parentId),
            'county' => $this->getSections($parentId),
            'section' => $this->getCities($parentId),
            'city' => $this->getRegions($parentId),
            'region' => $this->getNeighborhoods($parentId),
            'neighborhood' => $this->getStreets($parentId),
            'street' => $this->getAlleys($parentId),
            default => response()->json([])
        };
    }

    private function getCountries($continentId)
    {
        if (!Schema::hasTable('countries')) {
            return response()->json([]);
        }

        $query = Country::where('continent_id', $continentId);
        $this->applyStatusFilter($query, 'countries');

        return response()->json($query->get(['id', 'name']));
    }

    private function getProvinces($countryId)
    {
        if (!Schema::hasTable('provinces')) {
            return response()->json([]);
        }

        $query = Province::where('country_id', $countryId)->orderBy('name');
        $this->applyStatusFilter($query, 'provinces');

        return response()->json($query->get(['id', 'name']));
    }

    private function getCounties($provinceId)
    {
        if (!Schema::hasTable('counties')) {
            return response()->json([]);
        }

        return response()->json(
            County::where('province_id', $provinceId)
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    private function getSections($countyId)
    {
        if (!Schema::hasTable('districts')) {
            return response()->json([]);
        }

        $query = District::where('county_id', $countyId)->orderBy('name');
        $this->applyStatusFilter($query, 'districts');

        return response()->json($query->get(['id', 'name']));
    }

    private function getCities($sectionId)
    {
        if (!Schema::hasTable('cities') && !Schema::hasTable('rurals')) {
            return response()->json([]);
        }

        $cities = collect();
        if (Schema::hasTable('cities')) {
            $cityQuery = City::where('district_id', $sectionId)->orderBy('name');
            $this->applyStatusFilter($cityQuery, 'cities');
            $cities = $cityQuery->get(['id', 'name'])
                ->map(function ($city) {
                    return [
                        'id' => 'city_' . $city->id,
                        'name' => $city->name
                    ];
                });
        }

        $rurals = collect();
        if (Schema::hasTable('rurals')) {
            $ruralQuery = Rural::where('district_id', $sectionId)->orderBy('name');
            $this->applyStatusFilter($ruralQuery, 'rurals');
            $rurals = $ruralQuery->get(['id', 'name'])
                ->map(function ($rural) {
                    return [
                        'id' => 'rural_' . $rural->id,
                        'name' => $rural->name . ' (دهستان)'
                    ];
                });
        }

        return response()->json($cities->merge($rurals)->values());
    }

    private function getRegions($cityOrRuralId)
    {
        if (strpos($cityOrRuralId, 'rural_') === 0) {
            $ruralId = str_replace('rural_', '', $cityOrRuralId);
            if (!Schema::hasTable('villages')) {
                return response()->json([]);
            }

            $query = Village::where('rural_id', $ruralId)->orderBy('name');
            $this->applyStatusFilter($query, 'villages');

            return response()->json($query->get(['id', 'name']));
        } else {
            $cityId = str_replace('city_', '', $cityOrRuralId);
            if (!Schema::hasTable('regions')) {
                return response()->json([]);
            }

            // ساختار جدید: regions.parent_id = city.id
            $query = Region::where('parent_id', $cityId)->orderBy('name');
            $this->applyStatusFilter($query, 'regions');

            return response()->json($query->get(['id', 'name']));
        }
    }

    private function getNeighborhoods($regionId)
    {
        // نام جدول طبق مایگریشن: neighborhoods
        if (!Schema::hasTable('neighborhoods')) {
            return response()->json([]);
        }

        $query = Neighborhood::where('parent_id', $regionId)->orderBy('name');
        $this->applyStatusFilter($query, 'neighborhoods');

        return response()->json($query->get(['id', 'name']));
    }

    private function getStreets($neighborhoodId)
    {
        // نام جدول طبق مایگریشن: streets
        if (!Schema::hasTable('streets')) {
            return response()->json([]);
        }

        $query = Street::where('parent_id', $neighborhoodId)->orderBy('name');
        $this->applyStatusFilter($query, 'streets');

        return response()->json($query->get(['id', 'name']));
    }

    private function getAlleys($streetId)
    {
        // نام جدول طبق مایگریشن: alleies
        if (!Schema::hasTable('alleies')) {
            return response()->json([]);
        }

        $query = \App\Models\Alley::where('parent_id', $streetId)->orderBy('name');
        $this->applyStatusFilter($query, 'alleies');

        return response()->json($query->get(['id', 'name']));
    }

    private function applyStatusFilter($query, string $table): void
    {
        if (Schema::hasColumn($table, 'status')) {
            $query->where('status', 1);
        }
    }
}
