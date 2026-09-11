<?php

namespace Tests\Support\LocationGovernance;

use App\Models\Address;
use App\Models\City;
use App\Models\Continent;
use App\Models\Country;
use App\Models\County;
use App\Models\District;
use App\Models\Neighborhood;
use App\Models\Province;
use App\Models\Region;
use App\Models\Rural;
use App\Models\User;
use App\Models\Village;

final class LegacySpatialFixture
{
    public static function urbanSariUser(): User
    {
        $user = new User();
        $address = new Address([
            'continent_id' => 1,
            'country_id' => 1,
            'province_id' => 1,
            'county_id' => 1,
            'section_id' => 1,
            'city_id' => 1,
            'region_id' => 1,
            'neighborhood_id' => 1,
        ]);

        self::relate($address, 'continent', Continent::class, 1, 'آسیا');
        self::relate($address, 'country', Country::class, 1, 'ایران');
        self::relate($address, 'province', Province::class, 1, 'مازندران');
        self::relate($address, 'county', County::class, 1, 'ساری');
        self::relate($address, 'section', District::class, 1, 'مرکزی');
        self::relate($address, 'city', City::class, 1, 'ساری');
        self::relate($address, 'region', Region::class, 1, 'منطقه شهری');
        self::relate($address, 'neighborhood', Neighborhood::class, 1, 'محله نمونه');

        $user->setRelation('address', $address);

        return $user;
    }

    public static function ruralUserWithoutCity(): User
    {
        $user = new User();
        $address = new Address([
            'continent_id' => 1,
            'country_id' => 1,
            'province_id' => 1,
            'county_id' => 1,
            'section_id' => 1,
            'city_id' => null,
            'rural_id' => 1,
            'region_id' => null,
            'village_id' => 1,
            'neighborhood_id' => null,
        ]);

        self::relate($address, 'continent', Continent::class, 1, 'آسیا');
        self::relate($address, 'country', Country::class, 1, 'ایران');
        self::relate($address, 'province', Province::class, 1, 'مازندران');
        self::relate($address, 'county', County::class, 1, 'ساری');
        self::relate($address, 'section', District::class, 1, 'چهاردانگه');
        self::relate($address, 'rural', Rural::class, 1, 'دهستان نمونه');
        self::relate($address, 'village', Village::class, 1, 'روستای نمونه');

        $user->setRelation('address', $address);

        return $user;
    }

    /** @param class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
    private static function relate(Address $address, string $relation, string $modelClass, int $id, string $name): void
    {
        $model = new $modelClass();
        $model->forceFill(['id' => $id, 'name' => $name]);
        $address->setRelation($relation, $model);
    }
}
