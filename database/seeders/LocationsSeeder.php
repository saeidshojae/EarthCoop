<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use DB;

class LocationsSeeder extends Seeder
{
    public function run()
    {
        // سطح اول: قاره
        $continents = [
            'آسیا',
            'اروپا'
        ];

        foreach ($continents as $continent) {
            $continentId = DB::table('locations')->insertGetId([
                'name' => $continent,
                'parent_id' => null,
                'level' => 'continent',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // سطح دوم: کشور
            $countries = [
                'ایران',
                'چین'
            ];

            foreach ($countries as $country) {
                $countryId = DB::table('locations')->insertGetId([
                    'name' => $country,
                    'parent_id' => $continentId,
                    'level' => 'country',
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                // سطح سوم: استان
                $provinces = [
                    'تهران',
                    'اصفهان'
                ];

                foreach ($provinces as $province) {
                    $provinceId = DB::table('locations')->insertGetId([
                        'name' => $province,
                        'parent_id' => $countryId,
                        'level' => 'province',
                        'created_at' => now(),
                        'updated_at' => now()
                    ]);

                    // سطح چهارم: شهرستان
                    $counties = [
                        'شهرستان تهران',
                        'شهرستان اصفهان'
                    ];

                    foreach ($counties as $county) {
                        $countyId = DB::table('locations')->insertGetId([
                            'name' => $county,
                            'parent_id' => $provinceId,
                            'level' => 'county',
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);

                        // سطح پنجم: بخش
                        $sections = [
                            'بخش مرکزی',
                            'بخش شمالی'
                        ];

                        foreach ($sections as $section) {
                            $sectionId = DB::table('locations')->insertGetId([
                                'name' => $section,
                                'parent_id' => $countyId,
                                'level' => 'section',
                                'created_at' => now(),
                                'updated_at' => now()
                            ]);

                            // سطح ششم: شهر
                            $cities = [
                                'تهران',
                                'شیراز'
                            ];

                            foreach ($cities as $city) {
                                $cityId = DB::table('locations')->insertGetId([
                                    'name' => $city,
                                    'parent_id' => $sectionId,
                                    'level' => 'city',
                                    'created_at' => now(),
                                    'updated_at' => now()
                                ]);

                                // سطح هفتم: منطقه
                                $regions = [
                                    'منطقه 1',
                                    'منطقه 2'
                                ];

                                foreach ($regions as $region) {
                                    $regionId = DB::table('locations')->insertGetId([
                                        'name' => $region,
                                        'parent_id' => $cityId,
                                        'level' => 'region',
                                        'created_at' => now(),
                                        'updated_at' => now()
                                    ]);

                                    // سطح هشتم: محله
                                    $neighborhoods = [
                                        'محله 1',
                                        'محله 2'
                                    ];

                                    foreach ($neighborhoods as $neighborhood) {
                                        $neighborhoodId = DB::table('locations')->insertGetId([
                                            'name' => $neighborhood,
                                            'parent_id' => $regionId,
                                            'level' => 'neighborhood',
                                            'created_at' => now(),
                                            'updated_at' => now()
                                        ]);

                                        // سطح نهم: خیابان
                                        $streets = [
                                            'خیابان 1',
                                            'خیابان 2'
                                        ];

                                        foreach ($streets as $street) {
                                            $streetId = DB::table('locations')->insertGetId([
                                                'name' => $street,
                                                'parent_id' => $neighborhoodId,
                                                'level' => 'street',
                                                'created_at' => now(),
                                                'updated_at' => now()
                                            ]);

                                            // سطح دهم: کوچه
                                            $alleys = [
                                                'کوچه 1',
                                                'کوچه 2'
                                            ];

                                            foreach ($alleys as $alley) {
                                                DB::table('locations')->insert([
                                                    'name' => $alley,
                                                    'parent_id' => $streetId,
                                                    'level' => 'alley',
                                                    'created_at' => now(),
                                                    'updated_at' => now()
                                                ]);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
    }
}
