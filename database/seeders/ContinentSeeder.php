<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Continent;

class ContinentSeeder extends Seeder
{
    public function run()
    {
        $continents = [
            ['name_en' => 'Africa', 'name_local' => 'آفریقا', 'code' => 'AFR'],
            ['name_en' => 'Asia', 'name_local' => 'آسیا', 'code' => 'ASI'],
            ['name_en' => 'Europe', 'name_local' => 'اروپا', 'code' => 'EUR'],
            ['name_en' => 'North America', 'name_local' => 'آمریکای شمالی', 'code' => 'NAM'],
            ['name_en' => 'South America', 'name_local' => 'آمریکای جنوبی', 'code' => 'SAM'],
            ['name_en' => 'Australia', 'name_local' => 'استرالیا', 'code' => 'AUS'],
            ['name_en' => 'Antarctica', 'name_local' => 'جنوبگان', 'code' => 'ANT'],
        ];

        foreach ($continents as $continent) {
            Continent::create($continent);
        }
    }
}