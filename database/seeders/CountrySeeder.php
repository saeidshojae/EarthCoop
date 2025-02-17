<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Country;

class CountrySeeder extends Seeder
{
    public function run()
    {
        $countries = [
            ['name_en' => 'Iran', 'name_local' => 'ایران', 'iso2' => 'IR', 'iso3' => 'IRN', 'phone_code' => '+98', 'continent_id' => 2], // Assuming the ID of Asia is 2
            ['name_en' => 'Saudi Arabia', 'name_local' => 'عربستان سعودی', 'iso2' => 'SA', 'iso3' => 'SAU', 'phone_code' => '+966', 'continent_id' => 2],
            ['name_en' => 'United Arab Emirates', 'name_local' => 'امارات متحده عربی', 'iso2' => 'AE', 'iso3' => 'ARE', 'phone_code' => '+971', 'continent_id' => 2],
            ['name_en' => 'Qatar', 'name_local' => 'قطر', 'iso2' => 'QA', 'iso3' => 'QAT', 'phone_code' => '+974', 'continent_id' => 2],
            ['name_en' => 'Kuwait', 'name_local' => 'کویت', 'iso2' => 'KW', 'iso3' => 'KWT', 'phone_code' => '+965', 'continent_id' => 2],
            ['name_en' => 'Bahrain', 'name_local' => 'بحرین', 'iso2' => 'BH', 'iso3' => 'BHR', 'phone_code' => '+973', 'continent_id' => 2],
            ['name_en' => 'Oman', 'name_local' => 'عمان', 'iso2' => 'OM', 'iso3' => 'OMN', 'phone_code' => '+968', 'continent_id' => 2],
            ['name_en' => 'Iraq', 'name_local' => 'عراق', 'iso2' => 'IQ', 'iso3' => 'IRQ', 'phone_code' => '+964', 'continent_id' => 2],
            ['name_en' => 'Syria', 'name_local' => 'سوریه', 'iso2' => 'SY', 'iso3' => 'SYR', 'phone_code' => '+963', 'continent_id' => 2],
            ['name_en' => 'Lebanon', 'name_local' => 'لبنان', 'iso2' => 'LB', 'iso3' => 'LBN', 'phone_code' => '+961', 'continent_id' => 2],
            ['name_en' => 'Jordan', 'name_local' => 'اردن', 'iso2' => 'JO', 'iso3' => 'JOR', 'phone_code' => '+962', 'continent_id' => 2],
            ['name_en' => 'Palestine', 'name_local' => 'فلسطین', 'iso2' => 'PS', 'iso3' => 'PSE', 'phone_code' => '+970', 'continent_id' => 2],
            ['name_en' => 'Israel', 'name_local' => 'اسرائیل', 'iso2' => 'IL', 'iso3' => 'ISR', 'phone_code' => '+972', 'continent_id' => 2],
            ['name_en' => 'Turkey', 'name_local' => 'ترکیه', 'iso2' => 'TR', 'iso3' => 'TUR', 'phone_code' => '+90', 'continent_id' => 2],
            ['name_en' => 'Yemen', 'name_local' => 'یمن', 'iso2' => 'YE', 'iso3' => 'YEM', 'phone_code' => '+967', 'continent_id' => 2],
        ];

        foreach ($countries as $country) {
            Country::create($country);
        }
    }
}