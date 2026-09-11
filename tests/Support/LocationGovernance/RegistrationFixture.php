<?php

namespace Tests\Support\LocationGovernance;

use App\Models\Location;
use App\Models\LocationSchema;

final class RegistrationFixture
{
    /** @return array{schema: LocationSchema, endpoint: Location} */
    public static function urbanSari(): array
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'city'],
            ['ایران', 'مازندران', 'ساری', 'مرکزی', 'ساری'],
        );

        return ['schema' => $schema, 'endpoint' => $path->last()];
    }

    /** @return array{schema: LocationSchema, endpoint: Location} */
    public static function ruralVillage(): array
    {
        $schema = LocationFixture::iranSchema();
        $path = LocationFixture::createPath(
            $schema,
            ['country', 'province', 'county', 'section', 'rural_district', 'village'],
            ['ایران', 'مازندران', 'ساری', 'چهاردانگه', 'پشتکوه', 'نمونه‌روستا'],
        );

        return ['schema' => $schema, 'endpoint' => $path->last()];
    }

    /**
     * A village is already a valid residence endpoint in the Iran schema;
     * registration must not require a neighborhood to exist beneath it.
     *
     * @return array{schema: LocationSchema, endpoint: Location}
     */
    public static function villageWithoutNeighborhood(): array
    {
        return self::ruralVillage();
    }
}
